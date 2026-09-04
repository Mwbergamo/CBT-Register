<?php

namespace App\Services\ConnectWise;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Support\Facades\Config;

/**
 * Customer (ConnectWise "Company" + "Contact") search and creation, with the
 * retail auto-approval behaviour the register needs: a walk-in customer
 * presenting payment must be usable for invoicing immediately, without
 * waiting on the manual approval a terms/business-office account requires.
 *
 * "Approval" isn't a single universal ConnectWise field — it's whatever your
 * Manage instance uses to gate a new company for invoicing (commonly a
 * Company Status such as "Active"/"Approved", sometimes paired with a
 * Company Type). CONNECTWISE_RETAIL_COMPANY_STATUS_ID and
 * CONNECTWISE_RETAIL_COMPANY_TYPE_ID (config/connectwise.php) are what this
 * class applies on every retail customer it creates — set them to whatever
 * your Manage admin configures as the auto-approved retail status/type
 * (see docs/CONNECTWISE_SETUP.md). Never point these at the same
 * status/type used for CodeBlue's terms/business-office accounts.
 */
class CompanyService
{
    public function __construct(protected ConnectWiseClient $client) {}

    /**
     * Search ConnectWise directly (used when the local cache misses or is
     * stale). For normal register use, Customer::search() against the local
     * cache is what the UI calls — see App\Livewire\CustomerSearch.
     */
    public function searchRemote(string $term): array
    {
        $condition = sprintf(
            "(name like \"%%%s%%\") or (addressLine1 like \"%%%1\$s%%\")",
            addslashes($term)
        );

        return $this->client->get('company/companies', [
            'conditions' => $condition,
            'pageSize' => 25,
        ]);
    }

    /**
     * Create (or update) a ConnectWise company + primary contact for a new
     * walk-in customer, auto-approved for invoicing, then mirror it into the
     * local Customer cache. Throws ConnectWiseException on failure — callers
     * in the checkout flow should catch that and fall back to saving the
     * customer locally only, queuing the ConnectWise create for retry (the
     * checkout does not need to block on this the way it does on the final
     * invoice, since the invoice push itself will create the company first
     * if it's still missing a cw_company_id).
     */
    public function createRetailCustomer(array $data): Customer
    {
        $statusId = Config::get('connectwise.retail_company_status_id');
        $typeId = Config::get('connectwise.retail_company_type_id');

        $payload = [
            'name' => $data['company_name'] ?: trim($data['first_name'].' '.$data['last_name']),
            'identifier' => $this->makeIdentifier($data),
            'addressLine1' => $data['address_line1'] ?? null,
            'addressLine2' => $data['address_line2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip' => $data['zip'] ?? null,
            'phoneNumber' => $data['phone'] ?? null,
            'site' => ['name' => Config::get('connectwise.retail_company_site_name')],
        ];

        if ($statusId) {
            $payload['status'] = ['id' => (int) $statusId];
        }

        if ($typeId) {
            $payload['types'] = [['id' => (int) $typeId]];
        }

        $company = $this->client->post('company/companies', $payload);

        $contact = null;
        if (! empty($data['first_name']) || ! empty($data['last_name'])) {
            $contact = $this->client->post('company/contacts', [
                'firstName' => $data['first_name'] ?? '',
                'lastName' => $data['last_name'] ?? '',
                'company' => ['id' => $company['id']],
                'communicationItems' => array_filter([
                    ! empty($data['email']) ? [
                        'type' => ['name' => 'Email'],
                        'value' => $data['email'],
                        'defaultFlag' => true,
                    ] : null,
                    ! empty($data['phone']) ? [
                        'type' => ['name' => 'Direct'],
                        'value' => $data['phone'],
                        'defaultFlag' => true,
                    ] : null,
                ]),
            ]);
        }

        $customer = Customer::updateOrCreate(
            ['cw_company_id' => $company['id']],
            [
                'cw_contact_id' => $contact['id'] ?? null,
                'cw_site_id' => $company['site']['id'] ?? null,
                'company_name' => $company['name'],
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'address_line2' => $data['address_line2'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'zip' => $data['zip'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'cw_approved' => true,
                'cw_synced_at' => now(),
            ]
        );

        AuditLog::record('customer.created', context: [
            'customer_id' => $customer->id,
            'cw_company_id' => $company['id'],
            'auto_approved' => (bool) $statusId,
        ]);

        return $customer;
    }

    protected function makeIdentifier(array $data): string
    {
        // ConnectWise company "identifier" must be short/unique. Retail
        // walk-ins don't have a natural account code, so derive one.
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['last_name'] ?? $data['company_name'] ?? 'CUST'));
        $base = substr($base ?: 'CUST', 0, 8);

        return $base.substr((string) now()->timestamp, -6);
    }
}

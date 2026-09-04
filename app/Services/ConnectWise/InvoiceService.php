<?php

namespace App\Services\ConnectWise;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\SolutionsList;
use Illuminate\Support\Facades\Config;

/**
 * Turns a completed Solutions List into a ConnectWise Miscellaneous invoice
 * and closes it, per the retail checkout requirement: payment is already
 * presented at the register, so there is no reason for the invoice to sit
 * open.
 *
 * If the customer doesn't have a ConnectWise company yet (e.g. it was
 * queued for retry — see App\Jobs\PushSolutionsListToConnectWise), this
 * creates the company first via CompanyService so the whole push is a
 * single retryable unit of work.
 */
class InvoiceService
{
    public function __construct(
        protected ConnectWiseClient $client,
        protected CompanyService $companies,
    ) {}

    public function pushToConnectWise(SolutionsList $list): SolutionsList
    {
        $list->loadMissing(['customer', 'items']);
        $customer = $list->customer;

        if (! $customer->cw_company_id) {
            $customer = $this->companies->createRetailCustomer($customer->only([
                'company_name', 'first_name', 'last_name',
                'address_line1', 'address_line2', 'city', 'state', 'zip',
                'phone', 'email',
            ]));

            $list->customer()->associate($customer);
            $list->save();
        }

        $invoice = $this->createMiscInvoice($list, $customer);
        $this->closeInvoice($invoice['id']);

        $list->update([
            'status' => 'invoiced',
            'cw_invoice_id' => $invoice['id'],
            'cw_invoice_number' => $invoice['invoiceNumber'] ?? $invoice['id'],
            'invoiced_at' => now(),
        ]);

        AuditLog::record('invoice.created', context: [
            'solutions_list_id' => $list->id,
            'cw_invoice_id' => $invoice['id'],
            'total' => (string) $list->total,
        ]);

        return $list;
    }

    protected function createMiscInvoice(SolutionsList $list, Customer $customer): array
    {
        $payload = [
            'company' => ['id' => $customer->cw_company_id],
            'invoiceDate' => now()->toDateString(),
            'billAmount' => (float) $list->total,
            'billingTerms' => ['id' => Config::get('connectwise.gl_billing_account_id')] ?: null,
            // ConnectWise "Miscellaneous" invoices are line-item driven, not
            // agreement/ticket driven — this is the piece most likely to
            // need adjusting to your Manage version's exact invoice schema.
            'invoiceLineItems' => $list->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unitPrice' => (float) $item->unit_price,
                'taxableFlag' => $item->taxable,
            ])->values()->all(),
        ];

        return $this->client->post('finance/invoices', array_filter($payload, fn ($v) => $v !== null));
    }

    protected function closeInvoice(int|string $invoiceId): array
    {
        return $this->client->patch("finance/invoices/{$invoiceId}", [
            ['op' => 'replace', 'path' => '/closedFlag', 'value' => true],
        ]);
    }
}

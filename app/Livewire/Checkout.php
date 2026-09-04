<?php

namespace App\Livewire;

use App\Jobs\PushSolutionsListToConnectWise;
use App\Models\AuditLog;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\SolutionsList;
use App\Models\SolutionsListItem;
use App\Models\SyncQueueItem;
use App\Services\ConnectWise\CompanyService;
use App\Services\ConnectWise\ConnectWiseException;
use App\Services\ConnectWise\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The whole register screen as one component: find/enter the customer,
 * build the Solutions List from the Retail catalog, then check out. Kept
 * as a single component (rather than several talking over Livewire events)
 * because a register is a single continuous task for the associate and a
 * touchscreen wizard is easier to get right, and to keep an offline reader
 * of this code from having to trace cross-component wiring to see the
 * whole flow.
 *
 * Steps: 'customer' -> 'cart' -> 'complete'.
 */
class Checkout extends Component
{
    // --- Step 1: customer -------------------------------------------------
    public string $step = 'customer';

    public string $customerSearch = '';

    public ?int $selectedCustomerId = null;

    public bool $showNewCustomerForm = false;

    public string $newCompanyName = '';

    public string $newFirstName = '';

    public string $newLastName = '';

    public string $newAddressLine1 = '';

    public string $newAddressLine2 = '';

    public string $newCity = '';

    public string $newState = '';

    public string $newZip = '';

    public string $newPhone = '';

    public string $newEmail = '';

    // --- Step 2: cart -------------------------------------------------
    public ?int $solutionsListId = null;

    public string $productSearch = '';

    public string $manualDescription = '';

    public string $manualPrice = '';

    // --- Step 3: complete -------------------------------------------------
    public ?string $checkoutError = null;

    public string $paymentMethod = 'card';

    public string $paymentReference = '';

    #[Computed]
    public function customerResults()
    {
        if (trim($this->customerSearch) === '') {
            return collect();
        }

        return Customer::search($this->customerSearch)->orderBy('last_name')->limit(15)->get();
    }

    #[Computed]
    public function selectedCustomer(): ?Customer
    {
        return $this->selectedCustomerId ? Customer::find($this->selectedCustomerId) : null;
    }

    #[Computed]
    public function productResults()
    {
        if (trim($this->productSearch) === '') {
            return collect();
        }

        return CatalogItem::search($this->productSearch)->orderBy('description')->limit(20)->get();
    }

    #[Computed]
    public function solutionsList(): ?SolutionsList
    {
        return $this->solutionsListId
            ? SolutionsList::with(['items', 'customer'])->find($this->solutionsListId)
            : null;
    }

    public function selectCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->showNewCustomerForm = false;
    }

    public function startNewCustomer(): void
    {
        $this->showNewCustomerForm = true;
        $this->selectedCustomerId = null;

        // Carry over anything already typed in the search box as a starting
        // point — associates search using the same fields they'd enter.
        $this->newLastName = $this->customerSearch;
    }

    public function saveNewCustomer(CompanyService $companies): void
    {
        $data = $this->validate([
            'newCompanyName' => 'nullable|string|max:255',
            'newFirstName' => 'required_without:newCompanyName|string|max:100',
            'newLastName' => 'required_without:newCompanyName|string|max:100',
            'newAddressLine1' => 'nullable|string|max:255',
            'newAddressLine2' => 'nullable|string|max:255',
            'newCity' => 'nullable|string|max:100',
            'newState' => 'nullable|string|max:2',
            'newZip' => 'nullable|string|max:10',
            'newPhone' => 'nullable|string|max:25',
            'newEmail' => 'nullable|email|max:255',
        ], [], $this->fieldNameMap());

        $payload = [
            'company_name' => $data['newCompanyName'] ?: trim($data['newFirstName'].' '.$data['newLastName']),
            'first_name' => $data['newFirstName'],
            'last_name' => $data['newLastName'],
            'address_line1' => $data['newAddressLine1'],
            'address_line2' => $data['newAddressLine2'],
            'city' => $data['newCity'],
            'state' => $data['newState'],
            'zip' => $data['newZip'],
            'phone' => $data['newPhone'],
            'email' => $data['newEmail'],
        ];

        try {
            // Create (and auto-approve) in ConnectWise right away so the
            // associate sees a real, invoice-ready account immediately.
            $customer = $companies->createRetailCustomer($payload);
        } catch (ConnectWiseException $e) {
            // ConnectWise is unreachable — don't block the sale. Save the
            // customer locally; InvoiceService will create the ConnectWise
            // company as part of the queued invoice push at checkout.
            $customer = Customer::create($payload + ['cw_approved' => false]);

            $this->checkoutError = null; // surfaced contextually below, not blocking
            AuditLog::record('customer.created_local_only', context: [
                'customer_id' => $customer->id,
                'reason' => $e->getMessage(),
            ]);
        }

        $this->selectedCustomerId = $customer->id;
        $this->showNewCustomerForm = false;
    }

    protected function fieldNameMap(): array
    {
        return [
            'newFirstName' => 'first name',
            'newLastName' => 'last name',
            'newEmail' => 'email',
        ];
    }

    public function confirmCustomer(): void
    {
        $customer = $this->selectedCustomer;

        if (! $customer) {
            return;
        }

        $list = SolutionsList::firstOrCreate(
            ['customer_id' => $customer->id, 'status' => 'draft'],
            ['created_by_user_id' => Auth::id()]
        );

        $this->solutionsListId = $list->id;
        $this->step = 'cart';
    }

    public function changeCustomer(): void
    {
        $this->step = 'customer';
        $this->selectedCustomerId = null;
        $this->solutionsListId = null;
    }

    // --- Cart -------------------------------------------------------------

    public function addCatalogItem(int $catalogItemId): void
    {
        $item = CatalogItem::findOrFail($catalogItemId);
        $list = $this->solutionsList;

        if (! $list) {
            return;
        }

        $existing = $list->items->firstWhere('catalog_item_id', $item->id);

        if ($existing) {
            $existing->update([
                'quantity' => $existing->quantity + 1,
                'line_total' => ($existing->quantity + 1) * $existing->unit_price,
            ]);
        } else {
            $list->items()->create([
                'catalog_item_id' => $item->id,
                'identifier' => $item->identifier,
                'description' => $item->description,
                'quantity' => 1,
                'unit_price' => $item->price,
                'taxable' => $item->taxable,
                'line_total' => $item->price,
                'sort_order' => $list->items()->count(),
            ]);
        }

        $this->recalculate();
        $this->productSearch = '';
        unset($this->productResults);
    }

    public function addManualItem(): void
    {
        $this->validate([
            'manualDescription' => 'required|string|max:255',
            'manualPrice' => 'required|numeric|min:0',
        ]);

        $list = $this->solutionsList;

        if (! $list) {
            return;
        }

        $list->items()->create([
            'catalog_item_id' => null,
            'identifier' => null,
            'description' => $this->manualDescription,
            'quantity' => 1,
            'unit_price' => $this->manualPrice,
            'taxable' => true,
            'line_total' => $this->manualPrice,
            'sort_order' => $list->items()->count(),
        ]);

        $this->manualDescription = '';
        $this->manualPrice = '';
        $this->recalculate();
    }

    public function updateQuantity(int $itemId, int $quantity): void
    {
        $item = SolutionsListItem::findOrFail($itemId);

        if ($quantity < 1) {
            $item->delete();
        } else {
            $item->update(['quantity' => $quantity, 'line_total' => $quantity * $item->unit_price]);
        }

        $this->recalculate();
    }

    public function removeItem(int $itemId): void
    {
        SolutionsListItem::where('id', $itemId)->delete();
        $this->recalculate();
    }

    protected function recalculate(): void
    {
        $list = $this->solutionsList;

        if (! $list) {
            return;
        }

        unset($this->solutionsList);
        $list = $this->solutionsList;

        $list->recalculateTotals((float) config('register.tax_rate'));
        $list->save();
    }

    // --- Checkout -----------------------------------------------------

    public function checkout(InvoiceService $invoices): void
    {
        $list = $this->solutionsList;

        if (! $list || $list->items->isEmpty()) {
            $this->checkoutError = 'Add at least one item before checking out.';

            return;
        }

        $list->update([
            'status' => 'submitted',
            'payment_method' => $this->paymentMethod,
            'payment_reference' => $this->paymentReference,
        ]);

        try {
            $invoices->pushToConnectWise($list);
        } catch (ConnectWiseException $e) {
            // Hybrid workflow: the sale still completes locally. Queue the
            // ConnectWise push for automatic retry rather than making the
            // associate (or the customer, standing at the register) wait.
            $queueItem = SyncQueueItem::create([
                'solutions_list_id' => $list->id,
                'status' => 'pending',
                'next_attempt_at' => now(),
                'last_error' => $e->getMessage(),
            ]);

            PushSolutionsListToConnectWise::dispatch($queueItem->id);
        }

        unset($this->solutionsList);
        $this->step = 'complete';
    }

    public function startNewSale(): void
    {
        $this->reset([
            'step', 'customerSearch', 'selectedCustomerId', 'showNewCustomerForm',
            'newCompanyName', 'newFirstName', 'newLastName', 'newAddressLine1',
            'newAddressLine2', 'newCity', 'newState', 'newZip', 'newPhone', 'newEmail',
            'solutionsListId', 'productSearch', 'manualDescription', 'manualPrice',
            'checkoutError', 'paymentMethod', 'paymentReference',
        ]);
        $this->step = 'customer';
        $this->paymentMethod = 'card';
    }

    public function render()
    {
        return view('livewire.checkout');
    }
}

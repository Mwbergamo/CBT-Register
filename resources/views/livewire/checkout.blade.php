<div class="space-y-4" wire:loading.class="opacity-60">

    {{-- Step indicator --}}
    <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
        <span class="{{ $step === 'customer' ? 'text-brand-700' : '' }}">1. Customer</span>
        <span>&rsaquo;</span>
        <span class="{{ $step === 'cart' ? 'text-brand-700' : '' }}">2. Solutions List</span>
        <span>&rsaquo;</span>
        <span class="{{ $step === 'complete' ? 'text-brand-700' : '' }}">3. Done</span>
    </div>

    {{-- =================== STEP 1: CUSTOMER =================== --}}
    @if ($step === 'customer')
        <div class="bg-white rounded-2xl shadow p-4 sm:p-6 space-y-4">
            <h2 class="text-xl font-semibold">Find or enter customer</h2>

            <input
                type="text"
                wire:model.live.debounce.300ms="customerSearch"
                placeholder="Search by name, phone, email, or address..."
                class="w-full min-h-[52px] text-lg rounded-xl border-slate-300 focus:border-brand-600 focus:ring-brand-600"
                autofocus
            >

            @if (! $showNewCustomerForm && ! $selectedCustomer)
                <div class="space-y-2">
                    @forelse ($this->customerResults as $result)
                        <button type="button" wire:key="cust-{{ $result->id }}"
                            wire:click="selectCustomer({{ $result->id }})"
                            class="w-full text-left min-h-[64px] rounded-xl border border-slate-200 hover:border-brand-600 hover:bg-blue-50 px-4 py-3 flex flex-col">
                            <span class="font-medium">{{ $result->fullName() ?: $result->company_name }}</span>
                            <span class="text-sm text-slate-500">
                                {{ collect([$result->phone, $result->email, $result->address_line1])->filter()->join(' · ') }}
                            </span>
                        </button>
                    @empty
                        @if (trim($customerSearch) !== '')
                            <p class="text-slate-500 text-sm py-2">No matches for "{{ $customerSearch }}".</p>
                        @endif
                    @endforelse

                    <button type="button" wire:click="startNewCustomer"
                        class="w-full min-h-[56px] rounded-xl border-2 border-dashed border-slate-300 hover:border-brand-600 text-brand-700 font-medium">
                        + New customer
                    </button>
                </div>
            @endif

            {{-- Selected existing customer: fields autofill from ConnectWise/local cache --}}
            @if ($selectedCustomer && ! $showNewCustomerForm)
                <div class="rounded-xl border border-brand-600 bg-blue-50 p-4 space-y-3">
                    <p class="text-sm font-medium text-brand-700">Customer on file</p>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-slate-500">Name</dt><dd class="font-medium">{{ $selectedCustomer->fullName() ?: $selectedCustomer->company_name }}</dd></div>
                        <div><dt class="text-slate-500">Phone</dt><dd class="font-medium">{{ $selectedCustomer->phone ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ $selectedCustomer->email ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Address</dt><dd class="font-medium">{{ collect([$selectedCustomer->address_line1, $selectedCustomer->city, $selectedCustomer->state, $selectedCustomer->zip])->filter()->join(', ') ?: '—' }}</dd></div>
                    </dl>
                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="confirmCustomer"
                            class="min-h-[52px] px-6 rounded-xl bg-brand-700 hover:bg-brand-600 text-white font-medium">
                            Use this customer &rarr;
                        </button>
                        <button type="button" wire:click="$set('selectedCustomerId', null)"
                            class="min-h-[52px] px-6 rounded-xl border border-slate-300 text-slate-600">
                            Back to search
                        </button>
                    </div>
                </div>
            @endif

            {{-- New customer entry — same fields the associate searched on --}}
            @if ($showNewCustomerForm)
                <div class="rounded-xl border border-slate-200 p-4 space-y-3">
                    <p class="text-sm font-medium text-slate-700">New customer</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="First name"><input type="text" wire:model="newFirstName" class="cw-input"></x-field>
                        <x-field label="Last name"><input type="text" wire:model="newLastName" class="cw-input"></x-field>
                        <x-field label="Company (optional)"><input type="text" wire:model="newCompanyName" class="cw-input"></x-field>
                        <x-field label="Phone"><input type="tel" wire:model="newPhone" class="cw-input"></x-field>
                        <x-field label="Email"><input type="email" wire:model="newEmail" class="cw-input"></x-field>
                        <x-field label="Address line 1"><input type="text" wire:model="newAddressLine1" class="cw-input"></x-field>
                        <x-field label="Address line 2"><input type="text" wire:model="newAddressLine2" class="cw-input"></x-field>
                        <x-field label="City"><input type="text" wire:model="newCity" class="cw-input"></x-field>
                        <x-field label="State"><input type="text" wire:model="newState" maxlength="2" class="cw-input"></x-field>
                        <x-field label="ZIP"><input type="text" wire:model="newZip" class="cw-input"></x-field>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="saveNewCustomer" wire:loading.attr="disabled" wire:target="saveNewCustomer"
                            class="min-h-[52px] px-6 rounded-xl bg-brand-700 hover:bg-brand-600 text-white font-medium disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveNewCustomer">Save customer &amp; continue</span>
                            <span wire:loading wire:target="saveNewCustomer">Saving...</span>
                        </button>
                        <button type="button" wire:click="$set('showNewCustomerForm', false)"
                            class="min-h-[52px] px-6 rounded-xl border border-slate-300 text-slate-600">
                            Cancel
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- =================== STEP 2: SOLUTIONS LIST / CART =================== --}}
    @if ($step === 'cart' && $this->solutionsList)
        @php($list = $this->solutionsList)
        <div class="bg-white rounded-2xl shadow p-4 sm:p-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-slate-500">Customer</p>
                <p class="text-lg font-semibold">{{ $list->customer->fullName() ?: $list->customer->company_name }}</p>
            </div>
            <button type="button" wire:click="changeCustomer" class="min-h-[44px] px-4 rounded-lg border border-slate-300 text-sm text-slate-600">
                Change customer
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
            {{-- Product search --}}
            <div class="lg:col-span-3 bg-white rounded-2xl shadow p-4 sm:p-6 space-y-3">
                <h2 class="text-lg font-semibold">Add products &amp; services</h2>
                <input type="text" wire:model.live.debounce.250ms="productSearch"
                    placeholder="Search the Retail catalog..."
                    class="w-full min-h-[52px] text-lg rounded-xl border-slate-300 focus:border-brand-600 focus:ring-brand-600">

                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach ($this->productResults as $product)
                        <button type="button" wire:key="prod-{{ $product->id }}" wire:click="addCatalogItem({{ $product->id }})"
                            class="w-full text-left min-h-[60px] rounded-xl border border-slate-200 hover:border-brand-600 hover:bg-blue-50 px-4 py-2 flex items-center justify-between gap-3">
                            <span>
                                <span class="block font-medium">{{ $product->description }}</span>
                                <span class="block text-xs text-slate-500">{{ $product->identifier }}</span>
                            </span>
                            <span class="font-semibold whitespace-nowrap">${{ number_format($product->price, 2) }}</span>
                        </button>
                    @endforeach
                    @if (trim($productSearch) !== '' && $this->productResults->isEmpty())
                        <p class="text-slate-500 text-sm py-2">No retail items match "{{ $productSearch }}".</p>
                    @endif
                </div>

                <details class="pt-2">
                    <summary class="cursor-pointer text-sm text-slate-500">Add a manual / non-catalog line item</summary>
                    <div class="mt-3 flex flex-wrap gap-3 items-end">
                        <x-field label="Description" class="flex-1 min-w-[180px]">
                            <input type="text" wire:model="manualDescription" class="cw-input">
                        </x-field>
                        <x-field label="Price" class="w-32">
                            <input type="number" step="0.01" min="0" wire:model="manualPrice" class="cw-input">
                        </x-field>
                        <button type="button" wire:click="addManualItem" class="min-h-[48px] px-4 rounded-xl bg-slate-800 text-white font-medium">Add</button>
                    </div>
                </details>
            </div>

            {{-- Cart --}}
            <div class="lg:col-span-2 bg-white rounded-2xl shadow p-4 sm:p-6 space-y-3 flex flex-col">
                <h2 class="text-lg font-semibold">Solutions List</h2>

                <div class="flex-1 space-y-2 max-h-96 overflow-y-auto">
                    @forelse ($list->items as $item)
                        <div wire:key="item-{{ $item->id }}" class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2">
                            <div class="min-w-0">
                                <p class="font-medium truncate">{{ $item->description }}</p>
                                <p class="text-xs text-slate-500">${{ number_format($item->unit_price, 2) }} each</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})"
                                    class="w-10 h-10 rounded-lg border border-slate-300 text-lg font-bold">&minus;</button>
                                <span class="w-6 text-center font-medium">{{ $item->quantity }}</span>
                                <button type="button" wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})"
                                    class="w-10 h-10 rounded-lg border border-slate-300 text-lg font-bold">+</button>
                                <button type="button" wire:click="removeItem({{ $item->id }})"
                                    class="w-10 h-10 rounded-lg text-red-600 hover:bg-red-50 font-bold">&times;</button>
                            </div>
                        </div>
                    @empty
                        <p class="text-slate-400 text-sm py-6 text-center">No items yet — add something from the catalog.</p>
                    @endforelse
                </div>

                <div class="border-t border-slate-200 pt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span>${{ number_format($list->subtotal, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Tax</span><span>${{ number_format($list->tax_total, 2) }}</span></div>
                    <div class="flex justify-between text-lg font-semibold"><span>Total</span><span>${{ number_format($list->total, 2) }}</span></div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-field label="Payment method">
                        <select wire:model="paymentMethod" class="cw-input">
                            <option value="card">Card</option>
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                        </select>
                    </x-field>
                    <x-field label="Reference (optional)">
                        <input type="text" wire:model="paymentReference" class="cw-input">
                    </x-field>
                </div>

                @if ($checkoutError)
                    <p class="text-red-600 text-sm">{{ $checkoutError }}</p>
                @endif

                <button type="button" wire:click="checkout" wire:loading.attr="disabled" wire:target="checkout"
                    class="w-full min-h-[56px] rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-lg font-semibold disabled:opacity-50">
                    <span wire:loading.remove wire:target="checkout">Checkout — ${{ number_format($list->total, 2) }}</span>
                    <span wire:loading wire:target="checkout">Processing...</span>
                </button>
            </div>
        </div>
    @endif

    {{-- =================== STEP 3: COMPLETE =================== --}}
    @if ($step === 'complete')
        @php($list = \App\Models\SolutionsList::with('customer')->find($solutionsListId))
        <div class="bg-white rounded-2xl shadow p-6 sm:p-10 text-center space-y-4 max-w-lg mx-auto">
            <div class="mx-auto w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 text-3xl">&check;</div>
            <h2 class="text-2xl font-semibold">Sale complete — {{ $list->ticket_number }}</h2>

            @if ($list->status === 'invoiced')
                <p class="text-slate-600">ConnectWise invoice <strong>{{ $list->cw_invoice_number }}</strong> created and closed.</p>
            @else
                <p class="text-amber-700 bg-amber-50 rounded-lg px-4 py-3 text-sm">
                    Saved locally — ConnectWise couldn't be reached, so the invoice will be created automatically as soon as it's back. Nothing further to do.
                </p>
            @endif

            <div class="flex flex-col sm:flex-row gap-3 justify-center pt-4">
                <a href="{{ route('solutions-lists.print', $list) }}" target="_blank"
                    class="min-h-[52px] inline-flex items-center justify-center px-6 rounded-xl border border-slate-300 font-medium">
                    Print receipt
                </a>
                <button type="button" wire:click="startNewSale"
                    class="min-h-[52px] px-6 rounded-xl bg-brand-700 hover:bg-brand-600 text-white font-medium">
                    Start next sale
                </button>
            </div>
        </div>
    @endif
</div>

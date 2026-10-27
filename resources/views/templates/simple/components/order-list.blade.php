{{--
    The order list: one row per product with its name, code, price, stock, a quantity box and
    an add button. No imagery anywhere — this template is for clients who sell from a price
    list. Rows can also be ticked and added together.

    A product that varies by more than one value (two sizes, three colours) cannot be added
    straight from a row, because the customer has a choice to make; those rows link to the
    product page, where the options are picked. Products with a single pack size are added
    with that size filled in automatically.
--}}
@props(['products', 'showStock' => true, 'empty' => null])

@php
    $initialQty = [];
    foreach ($products as $product) {
        $initialQty[$product->id] = 1;
    }
@endphp

<div x-data="{
        qty: @js($initialQty),
        sel: {},
        busy: false,
        get selectedIds() { return Object.keys(this.sel).filter((id) => this.sel[id]); },
        clamp(id, max) {
            let n = parseInt(this.qty[id], 10);
            if (isNaN(n) || n < 1) n = 1;
            if (max && n > max) n = max;
            this.qty[id] = n;
        },
        async add(id, options, max, open = true) {
            this.clamp(id, max);
            this.busy = true;
            try {
                await this.$store.cart.add(id, null, null, this.qty[id], open, options);
            } finally {
                this.busy = false;
            }
        },
        async addSelected() {
            const ids = this.selectedIds;
            if (!ids.length) return;
            this.busy = true;
            try {
                for (const id of ids) {
                    const row = this.$refs['row' + id];
                    if (!row) continue;
                    const options = JSON.parse(row.dataset.options || 'null');
                    const max = parseInt(row.dataset.max, 10) || null;
                    if (!options) continue;
                    this.clamp(id, max);
                    await this.$store.cart.add(id, null, null, this.qty[id], false, options);
                }
                this.sel = {};
                this.$store.ui.openCart();
            } finally {
                this.busy = false;
            }
        },
    }">
    <table class="ol-table">
        <thead>
            <tr>
                <th scope="col" style="width:2.5rem"><span class="sr-only">Select</span></th>
                <th scope="col">Product</th>
                <th scope="col">Code</th>
                <th scope="col" class="ol-price">Price</th>
                @if($showStock)<th scope="col">Stock</th>@endif
                <th scope="col">Quantity</th>
                <th scope="col"><span class="sr-only">Add to order</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                @php
                    $attributes = $product->variantAttributes();
                    $singleValued = collect($attributes)->every(fn (array $attribute) => count($attribute['values']) === 1);
                    // Fillable straight from the row only when there is nothing to choose.
                    $options = $singleValued
                        ? collect($attributes)->mapWithKeys(fn (array $a) => [$a['name'] => $a['values'][0]])->all()
                        : null;
                    $max = $product->max_qty ?: ($product->stock > 0 ? $product->stock : null);
                    $stock = (int) $product->stock;
                @endphp
                <tr x-ref="row{{ $product->id }}"
                    data-options="{{ $options === null ? 'null' : json_encode($options) }}"
                    data-max="{{ $max }}">
                    <td>
                        @if($options && $stock > 0)
                            <input type="checkbox" x-model="sel[{{ $product->id }}]" aria-label="Select {{ $product->name }}">
                        @endif
                    </td>
                    <td class="ol-name">
                        <a href="{{ $product->url }}">{{ $product->name }}</a>
                        <span class="ol-sub">
                            {{ $product->category?->name }}
                            @if($unit = ($product->sizes[0] ?? null)) · {{ $unit }} @endif
                        </span>
                    </td>
                    <td class="ol-code" data-label="Code">{{ $product->sku ?: '—' }}</td>
                    <td class="ol-price" data-label="Price">{{ money($product->price) }}</td>
                    @if($showStock)
                        <td data-label="Stock">
                            <span class="ol-stock {{ $stock <= 0 ? 'is-out' : ($stock <= 10 ? 'is-low' : 'is-ok') }}">
                                {{ $stock <= 0 ? 'Out of stock' : number_format($stock).' in stock' }}
                            </span>
                        </td>
                    @endif
                    <td data-label="Quantity">
                        @if($options && $stock > 0)
                            <span class="ol-qty">
                                <button type="button" @click="qty[{{ $product->id }}] = Math.max(1, (qty[{{ $product->id }}] || 1) - 1)" aria-label="Decrease quantity">−</button>
                                <input type="number" min="1" @if($max) max="{{ $max }}" @endif
                                       x-model.number="qty[{{ $product->id }}]"
                                       @change="clamp({{ $product->id }}, {{ $max ?: 'null' }})"
                                       aria-label="Quantity for {{ $product->name }}">
                                <button type="button" @click="qty[{{ $product->id }}] = Math.min({{ $max ?: 9999 }}, (qty[{{ $product->id }}] || 1) + 1)" aria-label="Increase quantity">+</button>
                            </span>
                        @else
                            <span class="ol-sub">{{ $stock <= 0 ? 'Unavailable' : 'Options to choose' }}</span>
                        @endif
                    </td>
                    <td data-label="">
                        @if($stock <= 0)
                            <button type="button" class="ol-add" disabled>Out of stock</button>
                        @elseif($options)
                            <button type="button" class="ol-add"
                                    :disabled="busy"
                                    @click="add({{ $product->id }}, {{ json_encode($options) }}, {{ $max ?: 'null' }})">Add</button>
                        @else
                            <a href="{{ $product->url }}" class="ol-choose">Choose options</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showStock ? 7 : 6 }}" class="ol-empty">
                        {{ $empty ?: setting('site.empty_text', 'Nothing to show yet.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Only shown once something is ticked. --}}
    <div class="ol-bulk" x-show="selectedIds.length" x-cloak>
        <p><strong x-text="selectedIds.length"></strong> <span x-text="selectedIds.length === 1 ? 'product' : 'products'"></span> selected</p>
        <div style="display:flex;gap:.5rem">
            <button type="button" class="ol-add is-secondary" @click="sel = {}">Clear</button>
            <button type="button" class="ol-add" :disabled="busy" @click="addSelected()">Add selected to order</button>
        </div>
    </div>
</div>

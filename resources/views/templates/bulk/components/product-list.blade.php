{{--
    Products as a list (the shop and every category): one row per orderable item — each size of a product on its own
    row with its own code, price and stock — and a −/+ quantity box on every row. The buyer sets
    quantities down the list and adds them all at once from the bar that appears at the bottom.
    Rows come from App\Templates\Bulk\OrderLines, so what is added is exactly what is shown.
--}}
@props(['products', 'showStock' => true, 'group' => false])

@php
    $items = $products instanceof \Illuminate\Contracts\Pagination\Paginator ? collect($products->items()) : collect($products);
    $rows = $items->map(fn ($product) => ['product' => $product, 'lines' => \App\Templates\Bulk\OrderLines::for($product)]);
    $sheet = $rows->flatMap(fn ($row) => array_map(
        fn ($l) => \Illuminate\Support\Arr::only($l, ['key', 'product_id', 'options', 'price', 'max']),
        $row['lines'],
    ))->values();
    $columns = $showStock ? 5 : 4;
    $currentGroup = null;
@endphp

<div x-data="orderSheet(@js($sheet))">
    <div class="bk-card overflow-hidden">
        <table class="bk-table bk-list is-stack">
            <thead>
                <tr>
                    <th scope="col">Product</th>
                    <th scope="col">Code</th>
                    <th scope="col" class="is-num">Price</th>
                    @if($showStock)<th scope="col">Stock</th>@endif
                    <th scope="col">Quantity</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php $product = $row['product']; @endphp
                    @if($group && ($name = $product->category?->name) && $name !== $currentGroup)
                        @php $currentGroup = $name; @endphp
                        <tr class="bk-group"><th colspan="{{ $columns }}" scope="rowgroup">{{ $name }}</th></tr>
                    @endif
                    @forelse($row['lines'] as $line)
                        @php $key = $line['key']; @endphp
                        <tr :class="qty[@js($key)] > 0 && 'is-filled'">
                            <td>
                                <span class="flex items-center gap-3">
                                    <x-art :category="$product->category" :image="$product->image_url" class="bk-thumb !size-11" />
                                    <span class="min-w-0">
                                        <a href="{{ $product->url }}" class="block font-bold leading-snug hover:text-brand">{{ $product->name }}</a>
                                        @if($line['label'] || $line['unit'])
                                            <span class="bk-muted block text-xs">{{ $line['label'] ?? $line['unit'] }}</span>
                                        @endif
                                    </span>
                                </span>
                            </td>
                            <td class="bk-code" data-label="Code">{{ $line['sku'] ?: '—' }}</td>
                            <td class="is-num" data-label="Price">
                                <span class="font-extrabold">{{ $line['price_formatted'] }}</span>
                                @if($line['list_price'] > $line['price'])<span class="bk-was block">{{ money($line['list_price']) }}</span>@endif
                            </td>
                            @if($showStock)
                                <td data-label="Stock">
                                    <span class="bk-badge {{ $line['stock'] <= 0 ? 'bk-badge-out' : ($line['stock'] <= 10 ? 'bk-badge-low' : 'bk-badge-ok') }}">
                                        {{ $line['stock'] <= 0 ? 'Out of stock' : number_format($line['stock']).' in stock' }}
                                    </span>
                                </td>
                            @endif
                            <td data-label="Quantity">
                                @if($line['max'] > 0)
                                    <span class="bk-step">
                                        <button type="button" @click="step(@js($key), -1)" :disabled="!(qty[@js($key)] > 0)"
                                                aria-label="Decrease quantity of {{ $product->name }}"><x-heroicon-o-minus /></button>
                                        <input type="number" inputmode="numeric" min="0" max="{{ $line['max'] }}" placeholder="0"
                                               x-model.number="qty[@js($key)]" @change="clamp(@js($key))"
                                               @keydown.enter.prevent="clamp(@js($key)); addAll()"
                                               aria-label="Quantity of {{ $product->name }}{{ $line['label'] ? ', '.$line['label'] : '' }}">
                                        <button type="button" @click="step(@js($key), 1)" :disabled="(qty[@js($key)] || 0) >= {{ $line['max'] }}"
                                                aria-label="Increase quantity of {{ $product->name }}"><x-heroicon-o-plus /></button>
                                    </span>
                                    <span class="bk-error" x-show="errors[@js($key)]" x-text="errors[@js($key)]" x-cloak></span>
                                    <span class="bk-ok block" x-show="added[@js($key)]" x-text="added[@js($key)] + ' added'" x-cloak></span>
                                @else
                                    <span class="bk-out-text text-sm">Unavailable</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td>
                                <span class="flex items-center gap-3">
                                    <x-art :category="$product->category" :image="$product->image_url" class="bk-thumb !size-11" />
                                    <a href="{{ $product->url }}" class="font-bold hover:text-brand">{{ $product->name }}</a>
                                </span>
                            </td>
                            <td class="bk-code" data-label="Code">{{ $product->sku ?: '—' }}</td>
                            <td class="is-num font-extrabold" data-label="Price">from {{ money($product->price) }}</td>
                            @if($showStock)<td></td>@endif
                            <td><a href="{{ $product->url }}" class="bk-btn bk-btn-outline !py-2 text-xs">Choose options</a></td>
                        </tr>
                    @endforelse
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Appears once any quantity is set. --}}
    <div class="bk-sheetbar" x-show="filled.length" x-cloak
         x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100">
        <p>
            <strong x-text="filled.length"></strong> <span x-text="filled.length === 1 ? 'item' : 'items'"></span>,
            <strong x-text="units"></strong> <span x-text="units === 1 ? 'unit' : 'units'"></span>
            <span class="opacity-80">· about</span> <strong x-text="estimate"></strong>
        </p>
        <div class="flex gap-2">
            <button type="button" class="bk-btn !border-white/40 text-white hover:!bg-white/10" @click="clear()">Clear</button>
            <button type="button" class="bk-btn bk-btn-accent" :disabled="busy" @click="addAll()">
                <x-heroicon-o-shopping-cart aria-hidden="true" /><span x-text="busy ? 'Adding…' : 'Add all to order'">Add all to order</span>
            </button>
        </div>
    </div>
</div>

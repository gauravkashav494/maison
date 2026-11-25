{{--
    Products as a list (the shop and every category): one row per orderable item — each size of a product on its own
    row with its own code, price and stock — and a −/+ quantity box on every row. The buyer sets
    quantities down the list and adds them all at once from the bar that appears at the bottom.
    Hearts save to Saved items (the wishlist): a product with several sizes has one on every size
    row, saving exactly that size; a product with one row has one for the product.

    :saved (Saved items, App\Support\SavedItems::resolve()) lists what was saved instead of
    :products: a size saved is that size's row only; a product saved without a size is every size.
    A product with several rows gets one heading row (picture, name, heart, "4 sizes") with its
    sizes indented under it, and a size no longer sold stays on the list, marked so.
    Rows come from App\Templates\Bulk\OrderLines, so what is added is exactly what is shown.
--}}
@props(['products' => [], 'showStock' => true, 'group' => false, 'saved' => null])

@php
    $sameOptions = function (array $a, array $b): bool { ksort($a); ksort($b); return $a == $b; };
    if ($saved !== null) {
        $rows = collect($saved)->groupBy(fn (array $item) => $item['product']->id)->map(function ($items) use ($sameOptions) {
            $product = $items->first()['product'];
            $whole = $items->contains(fn (array $item) => ! $item['saved']);
            $lines = array_values(array_filter(\App\Templates\Bulk\OrderLines::for($product), fn (array $line) => $whole
                || $items->contains(fn (array $item) => $item['available'] && $sameOptions($line['options'], $item['options']))));

            return ['product' => $product, 'lines' => $lines, 'whole' => $whole, 'gone' => $whole ? [] : $items->where('available', false)->values()->all()];
        })->values();
    } else {
        $items = $products instanceof \Illuminate\Contracts\Pagination\Paginator ? collect($products->items()) : collect($products);
        $rows = $items->map(fn ($product) => ['product' => $product, 'lines' => \App\Templates\Bulk\OrderLines::for($product), 'whole' => true, 'gone' => []]);
    }
    $sheet = $rows->flatMap(fn ($row) => array_map(
        fn ($l) => \Illuminate\Support\Arr::only($l, ['key', 'product_id', 'options', 'price', 'max', 'limit_message']),
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
                    @php
                        $product = $row['product'];
                        // The options a buyer picks here (more than one value): what a size's heart saves.
                        $choiceNames = array_column(array_filter($product->variantAttributes(), fn (array $a) => count($a['values']) > 1), 'name');
                        $asGroup = $saved !== null && count($row['lines']) + count($row['gone']) > 1;
                        // A heart per size: on Saved items for each size saved (a product saved as a whole has
                        // only its heading heart); elsewhere on every row of a product with several sizes.
                        $sizeHearts = $saved !== null ? ! $row['whole'] : count($row['lines']) > 1;
                    @endphp
                    @if($group && ($name = $product->category?->name) && $name !== $currentGroup)
                        @php $currentGroup = $name; @endphp
                        <tr class="bk-group"><th colspan="{{ $columns }}" scope="rowgroup">{{ $name }}</th></tr>
                    @endif
                    @if($asGroup)
                        @php $count = count($row['lines']) + count($row['gone']); @endphp
                        <tr class="bk-group bk-product">
                            <th colspan="{{ $columns }}" scope="rowgroup">
                                <span class="flex items-center gap-3">
                                    <x-art :category="$product->category" :image="$product->image_url" class="bk-thumb !size-9" />
                                    <span class="min-w-0 flex-1">
                                        <a href="{{ $product->url }}" class="block font-bold leading-snug hover:text-brand">{{ $product->name }}</a>
                                        <span class="bk-muted block text-xs">{{ $row['whole'] ? 'All '.$count : $count }} {{ ($row['lines'][0]['label'] ?? $row['gone'][0]['label'] ?? null) ? 'sizes' : 'options' }}{{ $row['whole'] ? '' : ' saved' }}</span>
                                    </span>
                                    <x-wishlist-button :product="$product" />
                                </span>
                            </th>
                        </tr>
                    @endif
                    @forelse($row['lines'] as $line)
                        @php
                            $key = $line['key'];
                            $firstRow = $loop->first;
                            $choice = array_intersect_key($line['options'], array_flip($choiceNames));
                            $lastRow = $loop->last && ! $row['gone'];
                        @endphp
                        <tr :class="qty[@js($key)] > 0 && 'is-filled'" @class(['is-size' => $asGroup, 'is-last-size' => $asGroup && $lastRow])>
                            <td>
                                @if($asGroup)
                                    <span class="flex items-center gap-3">
                                        <span class="min-w-0 flex-1">
                                            <a href="{{ $product->url }}" class="block font-bold leading-snug hover:text-brand">{{ $product->name }}</a>
                                            <span class="bk-muted block text-xs">{{ $line['label'] ?? $line['unit'] }}</span>
                                        </span>
                                        @if($sizeHearts)<x-wishlist-button :product="$product" :options="$choice" />@endif
                                    </span>
                                @else
                                <span class="flex items-center gap-3">
                                    <x-art :category="$product->category" :image="$product->image_url" class="bk-thumb !size-9" />
                                    <span class="min-w-0 flex-1">
                                        <a href="{{ $product->url }}" class="block font-bold leading-snug hover:text-brand">{{ $product->name }}</a>
                                        @if($line['label'] || $line['unit'])
                                            <span class="bk-muted block text-xs">{{ $line['label'] ?? $line['unit'] }}</span>
                                        @endif
                                    </span>
                                    @if($sizeHearts)
                                        <x-wishlist-button :product="$product" :options="$choice" />
                                    @elseif($firstRow)
                                        <x-wishlist-button :product="$product" />
                                    @endif
                                </span>
                                @endif
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
                                               x-model.number="qty[@js($key)]" @change="change(@js($key))"
                                               @keydown.enter.prevent="$el.blur()"
                                               aria-label="Quantity of {{ $product->name }}{{ $line['label'] ? ', '.$line['label'] : '' }}">
                                        <button type="button" @click="step(@js($key), 1)"
                                                aria-label="Increase quantity of {{ $product->name }}"><x-heroicon-o-plus /></button>
                                    </span>
                                    <span class="bk-error" x-show="errors[@js($key)]" x-text="errors[@js($key)]" x-cloak></span>
                                @else
                                    <span class="bk-out-text text-sm">Unavailable</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        @if(! $row['gone'])
                        <tr>
                            <td>
                                <span class="flex items-center gap-3">
                                    <x-art :category="$product->category" :image="$product->image_url" class="bk-thumb !size-9" />
                                    <a href="{{ $product->url }}" class="flex-1 font-bold hover:text-brand">{{ $product->name }}</a>
                                    <x-wishlist-button :product="$product" />
                                </span>
                            </td>
                            <td class="bk-code" data-label="Code">{{ $product->sku ?: '—' }}</td>
                            <td class="is-num font-extrabold" data-label="Price">from {{ money($product->price) }}</td>
                            @if($showStock)<td></td>@endif
                            <td><a href="{{ $product->url }}" class="bk-btn bk-btn-outline !py-2 text-xs">Choose options</a></td>
                        </tr>
                        @endif
                    @endforelse
                    @foreach($row['gone'] as $item)
                        {{-- A saved size no longer sold: kept so the buyer sees what changed, with its heart to take it off. --}}
                        <tr @class(['is-size' => $asGroup, 'is-last-size' => $asGroup && $loop->last])>
                            <td>
                                <span class="flex items-center gap-3">
                                    @unless($asGroup)<x-art :category="$product->category" :image="$product->image_url" class="bk-thumb !size-9" />@endunless
                                    <span class="min-w-0 flex-1">
                                        <a href="{{ $product->url }}" class="block font-bold leading-snug hover:text-brand">{{ $product->name }}</a>
                                        <span class="bk-muted block text-xs">{{ implode(' / ', $item['saved']) }}</span>
                                    </span>
                                    <x-wishlist-button :product="$product" :options="$item['saved']" />
                                </span>
                            </td>
                            <td colspan="{{ $columns - 1 }}" data-label="Status"><span class="bk-out-text text-sm">No longer available</span></td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Quantities go straight into the cart; this shows what is in it. --}}
    <div class="bk-sheetbar" x-show="$store.cart.count > 0" x-cloak
         x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100">
        <p>
            <strong x-text="$store.cart.items.length"></strong> <span x-text="$store.cart.items.length === 1 ? 'item' : 'items'"></span>,
            <strong x-text="$store.cart.count"></strong> <span x-text="$store.cart.count === 1 ? 'unit' : 'units'"></span>
            <span class="opacity-80">·</span> <strong x-text="$store.cart.subtotal_formatted"></strong>
        </p>
        <a href="{{ route('cart') }}" class="bk-btn bk-btn-accent" @click.prevent="$store.ui.openCart()">
            <x-heroicon-o-shopping-cart aria-hidden="true" /><span>View cart</span>
        </a>
    </div>
</div>

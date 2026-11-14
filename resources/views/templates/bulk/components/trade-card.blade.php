{{--
    A product as a trade buyer sees it: picture, name, code, price, stock, and — right on the
    card — a pack-size picker when it comes in several, a quantity stepper and Add. What is
    added is one of the product's order lines (App\Templates\Bulk\OrderLines), so the price,
    code and stock shown always belong to the size chosen.
--}}
@props(['product', 'showStock' => true])

@php
    $lines = \App\Templates\Bulk\OrderLines::for($product);
    $first = collect($lines)->first(fn ($l) => $l['max'] > 0) ?? ($lines[0] ?? null);
    $inStock = collect($lines)->contains(fn ($l) => $l['max'] > 0);
    $state = array_map(fn ($l) => \Illuminate\Support\Arr::only($l, ['key', 'product_id', 'options', 'sku', 'label', 'price', 'price_formatted', 'list_price', 'stock', 'max']), $lines);
@endphp

<article class="bk-card bk-pcard" x-data="tradeCard(@js($state), @js($first['key'] ?? null))">
    <a href="{{ $product->url }}" class="relative block" tabindex="-1" aria-hidden="true">
        <x-art :category="$product->category" :image="$product->image_url" :alt="$product->name" />
        <span class="bk-pcard-badges">
            @if($first && $first['list_price'] > $first['price'])
                <span class="bk-badge bk-badge-deal">{{ (int) round(($first['list_price'] - $first['price']) * 100 / $first['list_price']) }}% off</span>
            @endif
            @if($product->is_new)<span class="bk-badge bk-badge-new">New</span>@endif
        </span>
    </a>

    <div class="bk-pcard-body">
        <div>
            <a href="{{ $product->url }}" class="bk-pcard-name">{{ $product->name }}</a>
            <p class="bk-code mt-1" x-text="line ? (line.sku || '') : ''">{{ $first['sku'] ?? $product->sku }}</p>
        </div>

        <div class="flex flex-wrap items-baseline gap-x-2">
            <span class="bk-price" x-text="line ? line.price_formatted : ''">{{ $first['price_formatted'] ?? money($product->price) }}</span>
            <span class="bk-was" x-show="line && line.list_price > line.price" x-text="line ? '₹' + Number(line.list_price).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : ''"
                  @if(! $first || $first['list_price'] <= $first['price']) x-cloak @endif>{{ $first && $first['list_price'] > $first['price'] ? money($first['list_price']) : '' }}</span>
            @if(count($product->sizes ?? []) === 1 && ! $product->sellsVariants())
                <span class="text-xs bk-muted">/ {{ $product->sizes[0] }}</span>
            @endif
        </div>

        @if(count($lines) > 1)
            <label class="sr-only" for="bk-size-{{ $product->id }}">Size</label>
            <select id="bk-size-{{ $product->id }}" class="bk-select !py-2 text-sm" x-model="key" @change="qty = 1; error = ''">
                @foreach($lines as $l)
                    <option value="{{ $l['key'] }}" @disabled($l['max'] <= 0)>{{ $l['label'] ?? $l['sku'] }} — {{ $l['price_formatted'] }}{{ $l['max'] <= 0 ? ' (out of stock)' : '' }}</option>
                @endforeach
            </select>
        @endif

        @if($showStock && $lines)
            <p>
                <span class="bk-badge" :class="!line || line.stock <= 0 ? 'bk-badge-out' : (line.stock <= 10 ? 'bk-badge-low' : 'bk-badge-ok')"
                      x-text="!line || line.stock <= 0 ? 'Out of stock' : line.stock.toLocaleString('en-IN') + ' in stock'">
                    {{ ($first['stock'] ?? 0) <= 0 ? 'Out of stock' : number_format($first['stock']).' in stock' }}
                </span>
            </p>
        @endif

        @if(! $lines)
            <a href="{{ $product->url }}" class="bk-btn bk-btn-outline bk-btn-block mt-auto">Choose options</a>
        @elseif(! $inStock)
            <button type="button" class="bk-btn bk-btn-outline bk-btn-out bk-btn-block mt-auto" disabled>Out of stock</button>
        @else
            <div class="bk-pcard-buy">
                <span class="bk-step">
                    <button type="button" @click="step(-1)" :disabled="qty <= 1" aria-label="Decrease quantity"><x-heroicon-o-minus /></button>
                    <input type="number" inputmode="numeric" min="1" :max="line?.max" x-model.number="qty" @change="step(0)" aria-label="Quantity of {{ $product->name }}">
                    <button type="button" @click="step(1)" :disabled="!line || qty >= line.max" aria-label="Increase quantity"><x-heroicon-o-plus /></button>
                </span>
                <button type="button" class="bk-btn bk-btn-primary" :disabled="busy || !line || line.max <= 0" @click="add()">
                    <x-heroicon-o-shopping-cart /><span x-text="busy ? 'Adding…' : 'Add'">Add</span>
                </button>
            </div>
            <p class="bk-error !mt-0" x-show="error" x-text="error" x-cloak></p>
        @endif
    </div>
</article>

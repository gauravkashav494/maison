{{-- Filter groups shared by the desktop sidebar and mobile sheet. Expects shopFilters() in scope. --}}
@php $group = 'border-t border-line py-4 first:border-t-0'; $head = 'text-xs font-extrabold uppercase tracking-wider text-ink'; $opt = 'flex cursor-pointer items-center gap-2.5 py-1.5 text-sm text-slate hover:text-ink'; @endphp
<div class="mt-2 divide-y divide-line">
    @if(!$category && $facets['categories']->isNotEmpty())
        <div class="py-4">
            <p class="{{ $head }}">Category</p>
            <div class="mt-2 max-h-56 overflow-y-auto">
                @foreach($facets['categories'] as $c)
                    <label class="{{ $opt }}"><input type="checkbox" class="check" :checked="has('category', @js($c->slug))" @change="toggle('category', @js($c->slug))"> {{ $c->name }}</label>
                @endforeach
            </div>
        </div>
    @endif

    @if($facets['subcategories']->isNotEmpty())
        <div class="py-4">
            <p class="{{ $head }}">Sub-category</p>
            <div class="mt-2 max-h-56 overflow-y-auto">
                @foreach($facets['subcategories'] as $c)
                    <label class="{{ $opt }}"><input type="checkbox" class="check" :checked="has('subcategory', @js($c->slug))" @change="toggle('subcategory', @js($c->slug))"> {{ $c->name }}</label>
                @endforeach
            </div>
        </div>
    @endif

    <div class="py-4">
        <p class="{{ $head }}">Price</p>
        <div class="mt-2">
            <label class="{{ $opt }}"><input type="radio" name="price" class="check" :checked="f.min === '' && f.max === ''" @change="setPrice('', '')"> Any price</label>
            @foreach($priceRanges as [$min, $max, $label])
                <label class="{{ $opt }}"><input type="radio" name="price" class="check" :checked="String(f.min) === @js((string) $min) && String(f.max) === @js((string) $max)" @change="setPrice(@js($min ?? ''), @js($max ?? ''))"> {{ $label }}</label>
            @endforeach
        </div>
    </div>

    @if($facets['brands']->isNotEmpty())
        <div class="py-4">
            <p class="{{ $head }}">Brand</p>
            <div class="mt-2 max-h-56 overflow-y-auto">
                @foreach($facets['brands'] as $brand => $count)
                    <label class="{{ $opt }}"><input type="checkbox" class="check" :checked="has('brand', @js($brand))" @change="toggle('brand', @js($brand))"> <span class="flex-1">{{ $brand }}</span><span class="text-xs text-mist">{{ $count }}</span></label>
                @endforeach
            </div>
        </div>
    @endif

    @if(count($facets['sizes']) > 1)
        <div class="py-4">
            <p class="{{ $head }}">Pack size</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach($facets['sizes'] as $size => $count)
                    <button type="button" @click="toggle('size', @js($size))" class="chip !py-1 text-xs" :class="has('size', @js($size)) && 'chip-active'">{{ $size }}</button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="py-4">
        <p class="{{ $head }}">Rating</p>
        <div class="mt-2">
            @foreach([4, 3] as $r)
                <label class="{{ $opt }}"><input type="radio" name="rating" class="check" :checked="String(f.rating) === '{{ $r }}'" @change="f.rating = '{{ $r }}'"> <x-ico name="star" :size="13" class="fill-star text-star" /> {{ $r }} & above</label>
            @endforeach
        </div>
    </div>

    <div class="py-4">
        <p class="{{ $head }}">More</p>
        <div class="mt-2">
            <label class="{{ $opt }}"><input type="checkbox" class="check" x-model="f.sale"> On offer</label>
            <label class="{{ $opt }}"><input type="checkbox" class="check" :checked="f.availability === 'in-stock'" @change="f.availability = $event.target.checked ? 'in-stock' : ''"> In stock only</label>
            @if($facets['collections']->isNotEmpty())
                @foreach($facets['collections'] as $col)
                    <label class="{{ $opt }}"><input type="checkbox" class="check" :checked="has('collection', @js($col->slug))" @change="toggle('collection', @js($col->slug))"> {{ $col->name }}</label>
                @endforeach
            @endif
        </div>
    </div>
</div>

{{-- Filter groups shared by the desktop sidebar and the mobile sheet. Expects shopFilters() in scope. --}}
@php
    $opt = 'flex cursor-pointer items-center gap-2.5 py-1.5 text-sm text-slate hover:text-ink';
    $groupOpen = fn (int $i) => $i < 3 ? 'true' : 'false';
    $i = 0;
@endphp
<div class="mt-2 divide-y divide-line-soft">
    @if(!$category && $facets['categories']->isNotEmpty())
        <div class="py-3" x-data="{ o: {{ $groupOpen($i++) }} }"><button type="button" @click="o = !o" class="filter-head" :aria-expanded="o">Category <x-ico name="chevron-down" :size="16" ::class="o && 'rotate-180'" class="transition-transform" /></button><div x-show="o" x-collapse class="max-h-56 overflow-y-auto pt-1">@foreach($facets['categories'] as $c)<label class="{{ $opt }}"><input type="checkbox" class="check" :checked="has('category', @js($c->slug))" @change="toggle('category', @js($c->slug))"> {{ $c->name }}</label>@endforeach</div></div>
    @endif
    @if($facets['subcategories']->isNotEmpty())
        <div class="py-3" x-data="{ o: {{ $groupOpen($i++) }} }"><button type="button" @click="o = !o" class="filter-head" :aria-expanded="o">Product type <x-ico name="chevron-down" :size="16" ::class="o && 'rotate-180'" class="transition-transform" /></button><div x-show="o" x-collapse class="max-h-56 overflow-y-auto pt-1">@foreach($facets['subcategories'] as $c)<label class="{{ $opt }}"><input type="checkbox" class="check" :checked="has('subcategory', @js($c->slug))" @change="toggle('subcategory', @js($c->slug))"> {{ $c->name }}</label>@endforeach</div></div>
    @endif
    <div class="py-3" x-data="{ o: {{ $groupOpen($i++) }} }">
        <button type="button" @click="o = !o" class="filter-head" :aria-expanded="o">Price <x-ico name="chevron-down" :size="16" ::class="o && 'rotate-180'" class="transition-transform" /></button>
        <div x-show="o" x-collapse class="pt-1">
            <label class="{{ $opt }}"><input type="radio" name="price" class="check" :checked="f.min === '' && f.max === ''" @change="setPrice('', '')"> Any price</label>
            @foreach($priceRanges as [$min, $max, $label])<label class="{{ $opt }}"><input type="radio" name="price" class="check" :checked="String(f.min) === @js((string) $min) && String(f.max) === @js((string) $max)" @change="setPrice(@js($min ?? ''), @js($max ?? ''))"> {{ $label }}</label>@endforeach
            <div class="mt-2 flex items-center gap-2"><input type="number" x-model="f.min" placeholder="Min ₹" class="field h-9 px-2 py-0 text-xs" aria-label="Minimum price"><span class="text-mist">–</span><input type="number" x-model="f.max" placeholder="Max ₹" class="field h-9 px-2 py-0 text-xs" aria-label="Maximum price"></div>
        </div>
    </div>
    @if($facets['brands']->isNotEmpty())
        <div class="py-3" x-data="{ o: {{ $groupOpen($i++) }} }"><button type="button" @click="o = !o" class="filter-head" :aria-expanded="o">Brand <x-ico name="chevron-down" :size="16" ::class="o && 'rotate-180'" class="transition-transform" /></button><div x-show="o" x-collapse class="max-h-56 overflow-y-auto pt-1">@foreach($facets['brands'] as $brand => $count)<label class="{{ $opt }}"><input type="checkbox" class="check" :checked="has('brand', @js($brand))" @change="toggle('brand', @js($brand))"> <span class="flex-1">{{ $brand }}</span><span class="text-xs text-mist">{{ $count }}</span></label>@endforeach</div></div>
    @endif
    @if(count($facets['sizes']) > 1)
        <div class="py-3" x-data="{ o: {{ $groupOpen($i++) }} }"><button type="button" @click="o = !o" class="filter-head" :aria-expanded="o">Size <x-ico name="chevron-down" :size="16" ::class="o && 'rotate-180'" class="transition-transform" /></button><div x-show="o" x-collapse class="flex flex-wrap gap-1.5 pt-2">@foreach($facets['sizes'] as $size => $count)<button type="button" @click="toggle('size', @js($size))" class="chip !py-1 text-xs" :class="has('size', @js($size)) && 'chip-active'">{{ $size }}</button>@endforeach</div></div>
    @endif
    @if($facets['materials']->isNotEmpty())
        <div class="py-3" x-data="{ o: {{ $groupOpen($i++) }} }"><button type="button" @click="o = !o" class="filter-head" :aria-expanded="o">Material <x-ico name="chevron-down" :size="16" ::class="o && 'rotate-180'" class="transition-transform" /></button><div x-show="o" x-collapse class="max-h-56 overflow-y-auto pt-1">@foreach($facets['materials'] as $material => $count)<label class="{{ $opt }}"><input type="checkbox" class="check" :checked="has('material', @js($material))" @change="toggle('material', @js($material))"> <span class="flex-1">{{ $material }}</span><span class="text-xs text-mist">{{ $count }}</span></label>@endforeach</div></div>
    @endif
    <div class="py-3" x-data="{ o: {{ $groupOpen($i++) }} }">
        <button type="button" @click="o = !o" class="filter-head" :aria-expanded="o">Discount <x-ico name="chevron-down" :size="16" ::class="o && 'rotate-180'" class="transition-transform" /></button>
        <div x-show="o" x-collapse class="pt-1">
            <label class="{{ $opt }}"><input type="radio" name="discount" class="check" :checked="!f.discount" @change="f.discount = ''"> Any</label>
            @foreach([10, 20, 30] as $d)<label class="{{ $opt }}"><input type="radio" name="discount" class="check" :checked="String(f.discount) === '{{ $d }}'" @change="f.discount = '{{ $d }}'"> {{ $d }}% or more</label>@endforeach
        </div>
    </div>
    <div class="py-3" x-data="{ o: {{ $groupOpen($i++) }} }">
        <button type="button" @click="o = !o" class="filter-head" :aria-expanded="o">Rating <x-ico name="chevron-down" :size="16" ::class="o && 'rotate-180'" class="transition-transform" /></button>
        <div x-show="o" x-collapse class="pt-1">@foreach([4, 3] as $r)<label class="{{ $opt }}"><input type="radio" name="rating" class="check" :checked="String(f.rating) === '{{ $r }}'" @change="f.rating = '{{ $r }}'"> <x-ico name="star" :size="13" class="fill-star text-star" /> {{ $r }} & above</label>@endforeach</div>
    </div>
    <div class="py-3" x-data="{ o: {{ $groupOpen($i++) }} }">
        <button type="button" @click="o = !o" class="filter-head" :aria-expanded="o">Availability <x-ico name="chevron-down" :size="16" ::class="o && 'rotate-180'" class="transition-transform" /></button>
        <div x-show="o" x-collapse class="pt-1">
            <label class="{{ $opt }}"><input type="checkbox" class="check" :checked="f.availability === 'in-stock'" @change="f.availability = $event.target.checked ? 'in-stock' : ''"> In stock only</label>
            <label class="{{ $opt }}"><input type="checkbox" class="check" x-model="f.sale"> On offer</label>
            @if($facets['collections']->isNotEmpty())@foreach($facets['collections'] as $col)<label class="{{ $opt }}"><input type="checkbox" class="check" :checked="has('collection', @js($col->slug))" @change="toggle('collection', @js($col->slug))"> {{ $col->name }}</label>@endforeach @endif
        </div>
    </div>
</div>

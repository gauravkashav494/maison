@php $mobile = $mobile ?? false; @endphp
@if($facets['categories']->isNotEmpty())
<div class="border-b border-ink/10 pb-6">
    <p class="eyebrow mb-4 text-taupe">Category</p>
    <ul class="space-y-2.5">
        @foreach($facets['categories'] as $c)
            <li><label class="flex cursor-pointer items-center gap-3 text-sm"><input type="checkbox" class="accent-ink" :checked="has('category', @js($c->slug))" @change="toggle('category', @js($c->slug))"> {{ $c->name }}</label></li>
        @endforeach
    </ul>
</div>
@endif

@if($facets['subcategories']->isNotEmpty())
<div class="border-b border-ink/10 pb-6">
    <p class="eyebrow mb-4 text-taupe">{{ $category?->name }} types</p>
    <ul class="space-y-2.5">
        @foreach($facets['subcategories'] as $c)
            <li><label class="flex cursor-pointer items-center gap-3 text-sm"><input type="checkbox" class="accent-ink" :checked="has('subcategory', @js($c->slug))" @change="toggle('subcategory', @js($c->slug))"> {{ $c->name }}</label></li>
        @endforeach
    </ul>
</div>
@endif

@if(count($facets['sizes']))
<div class="border-b border-ink/10 pb-6">
    <p class="eyebrow mb-4 text-taupe">Size</p>
    <div class="flex flex-wrap gap-2">
        @foreach($facets['sizes'] as $size => $n)
            <button type="button" @click="toggle('size', @js((string) $size))" :class="has('size', @js((string) $size)) ? 'border-ink bg-ink text-ivory' : 'border-ink/20 hover:border-ink'" class="min-w-11 border px-2.5 py-2 text-[0.6875rem] uppercase tracking-wider transition-colors">{{ $size }}</button>
        @endforeach
    </div>
</div>
@endif

@if(count($facets['colors']))
<div class="border-b border-ink/10 pb-6">
    <p class="eyebrow mb-4 text-taupe">Colour</p>
    <div class="flex flex-wrap gap-3">
        @foreach($facets['colors'] as $name => $c)
            <button type="button" @click="toggle('color', @js($name))" :aria-pressed="has('color', @js($name))" title="{{ $name }} ({{ $c['count'] }})" :class="has('color', @js($name)) ? 'ring-ink' : 'ring-ink/15 hover:ring-ink/40'" class="h-7 w-7 rounded-full ring-1 ring-offset-2 ring-offset-ivory transition-all" style="background:{{ $c['hex'] }}"></button>
        @endforeach
    </div>
</div>
@endif

<div class="border-b border-ink/10 pb-6">
    <p class="eyebrow mb-4 text-taupe">Price</p>
    <div class="flex items-center gap-3 text-sm">
        <label class="flex flex-1 items-center gap-1 border-b border-ink/30"><span class="text-taupe">₹</span><input type="number" x-model="f.min" placeholder="{{ number_format($facets['price_min']) }}" min="0" class="w-full bg-transparent py-1.5 outline-none"></label>
        <span class="text-taupe">–</span>
        <label class="flex flex-1 items-center gap-1 border-b border-ink/30"><span class="text-taupe">₹</span><input type="number" x-model="f.max" placeholder="{{ number_format($facets['price_max']) }}" min="0" class="w-full bg-transparent py-1.5 outline-none"></label>
    </div>
</div>

@if($facets['collections']->isNotEmpty())
<div class="border-b border-ink/10 pb-6">
    <p class="eyebrow mb-4 text-taupe">Collection</p>
    <ul class="space-y-2.5">
        @foreach($facets['collections'] as $c)
            <li><label class="flex cursor-pointer items-center gap-3 text-sm"><input type="checkbox" class="accent-ink" :checked="has('collection', @js($c->slug))" @change="toggle('collection', @js($c->slug))"> {{ $c->name }}</label></li>
        @endforeach
    </ul>
</div>
@endif

@if($facets['brands']->count() > 1)
<div class="border-b border-ink/10 pb-6">
    <p class="eyebrow mb-4 text-taupe">Brand</p>
    <ul class="space-y-2.5">
        @foreach($facets['brands'] as $b => $n)
            <li><label class="flex cursor-pointer items-center gap-3 text-sm"><input type="checkbox" class="accent-ink" :checked="has('brand', @js($b))" @change="toggle('brand', @js($b))"> {{ $b }} <span class="text-[0.625rem] text-taupe">({{ $n }})</span></label></li>
        @endforeach
    </ul>
</div>
@endif

@if($facets['materials']->isNotEmpty())
<div class="border-b border-ink/10 pb-6">
    <p class="eyebrow mb-4 text-taupe">Material</p>
    <ul class="space-y-2.5">
        @foreach($facets['materials'] as $m => $n)
            <li><label class="flex cursor-pointer items-center gap-3 text-sm"><input type="checkbox" class="accent-ink" :checked="has('material', @js($m))" @change="toggle('material', @js($m))"> {{ $m }}</label></li>
        @endforeach
    </ul>
</div>
@endif

<div class="border-b border-ink/10 pb-6">
    <p class="eyebrow mb-4 text-taupe">Rating</p>
    <div class="flex flex-wrap gap-2">
        @foreach([4.5, 4, 3] as $r)
            <button type="button" @click="f.rating = f.rating == @js($r) ? '' : @js($r)" :class="f.rating == @js($r) ? 'border-ink bg-ink text-ivory' : 'border-ink/20'" class="border px-3 py-2 text-[0.6875rem] tracking-wider">★ {{ $r }}+</button>
        @endforeach
    </div>
</div>

<div class="pb-2">
    <p class="eyebrow mb-4 text-taupe">Availability</p>
    <label class="flex cursor-pointer items-center gap-3 text-sm"><input type="checkbox" class="accent-ink" :checked="f.availability === 'in-stock'" @change="f.availability = f.availability === 'in-stock' ? '' : 'in-stock'"> In stock only</label>
    <label class="mt-2.5 flex cursor-pointer items-center gap-3 text-sm"><input type="checkbox" class="accent-ink" x-model="f.sale"> On sale</label>
</div>

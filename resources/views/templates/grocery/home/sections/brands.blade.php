@if($brands->isNotEmpty())
<section class="g-container section">
    <x-section-head :title="$g['brands_heading'] ?? 'Top brands'" />
    <div class="no-scrollbar -mx-4 flex gap-2.5 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0">
        @foreach($brands as $b)
            <a href="{{ route('shop.index', ['brand' => $b->brand]) }}" class="card card-hover flex shrink-0 items-center gap-3 px-4 py-3">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-leaf-light text-sm font-extrabold text-leaf-dark">{{ Str::upper(Str::substr($b->brand, 0, 1)) }}</span>
                <span><span class="block text-sm font-bold">{{ $b->brand }}</span><span class="block text-xs text-slate">{{ $b->products }} {{ Str::plural('product', $b->products) }}</span></span>
            </a>
        @endforeach
    </div>
</section>
@endif

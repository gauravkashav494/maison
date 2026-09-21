{{-- Trusted brands: read from the products' brand field; each links to the brand filter on the shop --}}
@if($brands->isNotEmpty())
<section class="bg-white section">
    <div class="p-container">
        <x-section-head :title="$g['brands_heading'] ?? 'Trusted brands'" :text="$g['brands_text'] ?? null" />
        <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            @foreach($brands as $b)
                <a href="{{ route('shop.index', ['brand' => $b->brand]) }}" class="card card-hover flex flex-col items-center justify-center gap-1 px-3 py-5 text-center">
                    <span class="grid h-11 w-11 place-items-center rounded-full bg-sky font-display text-lg font-extrabold text-deep">{{ Str::upper(Str::substr($b->brand, 0, 1)) }}</span>
                    <span class="mt-1 text-sm font-bold leading-tight">{{ $b->brand }}</span>
                    <span class="text-xs text-slate">{{ $b->products }} {{ Str::plural('product', $b->products) }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

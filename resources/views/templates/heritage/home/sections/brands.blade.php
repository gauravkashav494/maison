@if($brands->isNotEmpty())
<section class="h-container section">
    <x-section-head :eyebrow="$g['brands_eyebrow'] ?? 'Popular brands'" :title="$g['brands_heading'] ?? 'Houses we trust'" center />
    <div class="no-scrollbar -mx-4 flex gap-3 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:justify-center sm:px-0">
        @foreach($brands as $b)
            @php $logo = \App\Support\Media::url($b['logo'] ?? null); @endphp
            <a href="{{ $b['url'] ?? route('shop.index', ['brand' => $b['name']]) }}" class="card card-hover flex shrink-0 items-center gap-3 px-5 py-3.5">
                @if($logo)<img src="{{ $logo }}" alt="{{ $b['name'] }}" class="h-8 w-auto object-contain">@else<span class="grid h-10 w-10 place-items-center rounded-full border border-gold bg-cream font-serif text-base font-semibold text-red">{{ Str::upper(Str::substr($b['name'], 0, 1)) }}</span>@endif
                <span><span class="block font-serif text-base font-semibold">{{ $b['name'] }}</span>@if(!empty($b['count']))<span class="block text-xs text-muted">{{ $b['count'] }} {{ Str::plural('product', $b['count']) }}</span>@endif</span>
            </a>
        @endforeach
    </div>
</section>
@endif

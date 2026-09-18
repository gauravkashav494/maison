@if($deals->isNotEmpty())
<section class="section bg-berry-light/60">
    <div class="g-container">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="section-title flex items-center gap-2"><x-ico name="bolt" :size="22" class="text-berry" /> {{ $g['deals_heading'] ?? 'Deals of the day' }}</h2>
                @if(!empty($g['deals_text']))<p class="mt-0.5 text-sm text-slate">{{ $g['deals_text'] }}</p>@endif
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-berry tabular" x-data="countdown()">Ends in <span x-text="text">--:--:--</span></span>
                <a href="{{ route('shop.category', 'sale') }}" class="section-link">See all <x-ico name="chevron-right" :size="16" /></a>
            </div>
        </div>
        <x-rail :products="$deals" />
    </div>
</section>
@endif

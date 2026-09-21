{{-- Why choose us: compact cards (rail on phones, 3-column grid on desktop) --}}
@php $items = collect($g['why_items'] ?? [])->filter(fn ($s) => ! empty($s['title'])); @endphp
@if($items->isNotEmpty())
<section class="section bg-white">
    <div class="ps-container">
        <x-section-head :title="$g['why_heading'] ?? 'Why choose us?'" eyebrow="Trusted local plumbers" />
        <div class="rail rail-bleed no-scrollbar lg:grid lg:grid-cols-3 lg:gap-5 lg:overflow-visible lg:p-0 lg:mx-0">
            @foreach($items as $it)
                <div class="tile w-[14rem] p-4 lg:w-auto lg:p-6">
                    <span class="trust-ico"><x-ico :name="$it['icon'] ?? 'check'" :size="20" /></span>
                    <h3 class="mt-3 font-display text-base font-extrabold">{{ $it['title'] }}</h3>
                    @if(!empty($it['text']))<p class="mt-1 text-sm leading-relaxed text-slate">{{ $it['text'] }}</p>@endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
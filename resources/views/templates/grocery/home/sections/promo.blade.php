{{-- Promo banner: editable eyebrow, heading, text, button and image (Appearance → Grocery · Homepage) --}}
@if(!empty($g['promo_heading']))
<section class="g-container section">
    <div class="grid overflow-hidden rounded-2xl bg-leaf text-white lg:grid-cols-2">
        <div class="p-6 sm:p-10 lg:p-14">
            @if(!empty($g['promo_eyebrow']))<p class="inline-block rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-wider">{{ $g['promo_eyebrow'] }}</p>@endif
            <h2 class="mt-3 text-2xl font-extrabold leading-tight sm:text-3xl">{{ $g['promo_heading'] }}</h2>
            @if(!empty($g['promo_text']))<p class="mt-2 max-w-md text-sm text-white/85 sm:text-base">{{ $g['promo_text'] }}</p>@endif
            @if(!empty($g['promo_cta_label']))
                <div class="mt-6"><a href="{{ $g['promo_cta_url'] ?? route('shop.index') }}" class="btn bg-white text-ink hover:bg-paper">{{ $g['promo_cta_label'] }} <x-ico name="arrow-right" :size="16" /></a></div>
            @endif
        </div>
        @if(!empty($g['promo_image']))<div class="relative hidden min-h-[16rem] lg:block"><img src="{{ \App\Support\Media::url($g['promo_image']) }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy"></div>@endif
    </div>
</section>
@endif

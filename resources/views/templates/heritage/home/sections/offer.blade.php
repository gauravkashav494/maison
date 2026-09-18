@if(!empty($g['offer_heading']))
<section class="h-container section !pt-0">
    <div class="gold-frame relative grid overflow-hidden rounded-2xl panel-red lg:grid-cols-2">
        <div class="relative p-8 sm:p-12 lg:p-16">
            <div class="flex flex-wrap items-center gap-3">
                @if(!empty($g['offer_eyebrow']))<p class="eyebrow text-gold-light">{{ $g['offer_eyebrow'] }}</p>@endif
                @if(!empty($g['offer_badge']))<span class="badge badge-gold">{{ $g['offer_badge'] }}</span>@endif
            </div>
            <h2 class="display mt-4 text-3xl text-white sm:text-4xl lg:text-5xl">{{ $g['offer_heading'] }}</h2>
            @if(!empty($g['offer_text']))<p class="mt-4 max-w-md text-base text-white/85">{{ $g['offer_text'] }}</p>@endif
            @if(!empty($g['offer_cta_label']))<a href="{{ $g['offer_cta_url'] ?? '#' }}" class="btn btn-gold btn-lg mt-8">{{ $g['offer_cta_label'] }} <x-ico name="arrow-right" :size="16" /></a>@endif
        </div>
        <div class="relative min-h-[16rem] lg:min-h-0">
            @if(!empty($g['offer_image']))<img src="{{ \App\Support\Media::url($g['offer_image']) }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">@endif
            <div class="absolute inset-0 bg-gradient-to-r from-red/60 to-transparent lg:from-red/40"></div>
        </div>
    </div>
</section>
@endif

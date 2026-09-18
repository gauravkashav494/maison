@if(!empty($g['offer_heading']))
<section class="h-container section !py-8 lg:!py-10">
    <div class="banner-round relative min-h-[16rem] bg-maroon-deep text-cream lg:min-h-[20rem]">
        @if(!empty($g['offer_image']))<img src="{{ \App\Support\Media::url($g['offer_image']) }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">@endif
        <div class="absolute inset-0 bg-gradient-to-r from-maroon-deep/90 via-maroon-deep/55 to-transparent"></div>
        <div class="relative flex h-full min-h-[16rem] flex-col justify-center p-8 sm:p-12 lg:min-h-[20rem] lg:max-w-xl lg:p-16">
            @if(!empty($g['offer_badge']))<span class="badge badge-gold self-start">{{ $g['offer_badge'] }}</span>@endif
            <h2 class="display mt-3 text-3xl text-cream sm:text-4xl">{{ $g['offer_heading'] }}</h2>
            @if(!empty($g['offer_text']))<p class="mt-3 text-sm text-cream/85 sm:text-base">{{ $g['offer_text'] }}</p>@endif
            @if(!empty($g['offer_cta_label']))<div class="mt-6"><a href="{{ $g['offer_cta_url'] ?? '#' }}" class="btn btn-gold rounded-full px-7">{{ $g['offer_cta_label'] }} <x-ico name="arrow-right" :size="16" /></a></div>@endif
        </div>
    </div>
</section>
@endif

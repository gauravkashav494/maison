@if(!empty($g['banner_heading']))
<section class="g-container section">
    <a href="{{ $g['banner_cta_url'] ?? '#' }}" class="group relative block overflow-hidden rounded-2xl bg-ink text-white">
        @if(!empty($g['banner_image']))<img src="{{ \App\Support\Media::url($g['banner_image']) }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-60 transition-transform duration-700 group-hover:scale-105" loading="lazy">@endif
        <div class="absolute inset-0 bg-gradient-to-r from-ink/85 via-ink/50 to-transparent"></div>
        <div class="relative p-6 sm:p-10 lg:max-w-xl lg:p-14">
            <h2 class="text-2xl font-extrabold leading-tight sm:text-3xl">{{ $g['banner_heading'] }}</h2>
            @if(!empty($g['banner_text']))<p class="mt-2 text-sm text-white/80 sm:text-base">{{ $g['banner_text'] }}</p>@endif
            @if(!empty($g['banner_cta_label']))<span class="btn btn-accent mt-5">{{ $g['banner_cta_label'] }} <x-ico name="arrow-right" :size="16" /></span>@endif
        </div>
    </a>
</section>
@endif

@php $video = $g['video_url'] ?? null; $poster = \App\Support\Media::url($g['video_poster'] ?? null); $hs = tsetting('site'); @endphp
@if($poster)
<section class="bg-warm pb-6 pt-10 lg:pb-0 lg:pt-[60px]" x-data="{ playing: false, url: @js($video), isYouTube: /youtube\.com|youtu\.be/.test(@js($video ?? '')), embed() { const m = this.url.match(/(?:v=|youtu\.be\/|embed\/)([\w-]{6,})/); return m ? `https://www.youtube.com/embed/${m[1]}?autoplay=1&rel=0` : this.url; } }">
    <div class="h-container"><div class="banner-round relative mx-auto aspect-[16/9] w-full max-w-[1160px] bg-maroon-deep">
        <template x-if="!playing">
            <div class="absolute inset-0">
                <img src="{{ $poster }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                <span class="absolute inset-0 bg-gradient-to-t from-maroon-deep/50 to-transparent"></span>
                <span class="absolute left-5 top-5 grid h-14 w-14 place-items-center rounded-full border-2 border-gold bg-cream text-red lg:left-8 lg:top-8 lg:h-[4.5rem] lg:w-[4.5rem]"><span class="flex flex-col items-center leading-none"><x-ico name="diamond" :size="14" :stroke="1.8" /><span class="mt-1 font-serif text-[0.55rem] font-semibold uppercase tracking-[0.1em]">{{ Str::substr($hs['logo_primary'] ?? 'Annapurna', 0, 9) }}</span><span class="text-[0.45rem] uppercase tracking-[0.2em] text-gold">{{ $hs['logo_sub'] ?? '' }}</span></span></span>
                @if($video)<button type="button" @click="playing = true" class="group absolute inset-0 h-full w-full" aria-label="Play video"><span class="absolute left-1/2 top-1/2 grid h-16 w-16 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-red shadow-float transition-transform group-hover:scale-105"><x-ico name="play" :size="24" /></span></button>@endif
            </div>
        </template>
        <template x-if="playing">
            <div class="absolute inset-0">
                <template x-if="isYouTube"><iframe :src="embed()" class="h-full w-full" allow="autoplay; encrypted-media" allowfullscreen title="Brand video"></iframe></template>
                <template x-if="!isYouTube"><video :src="url" controls autoplay playsinline class="h-full w-full object-cover"></video></template>
            </div>
        </template>
    </div></div>
    <div class="mt-5 flex h-[29px] items-center justify-center gap-1.5" aria-hidden="true"><span class="h-2 w-2 rounded-full bg-red"></span><span class="h-2 w-2 rounded-full bg-gold/50"></span></div>
</section>
@endif

@php $video = $g['video_url'] ?? null; $poster = \App\Support\Media::url($g['video_poster'] ?? null); @endphp
@if($video && $poster)
<section class="h-container section !py-8 lg:!py-10" x-data="{ playing: false, isYouTube: /youtube\.com|youtu\.be/.test(@js($video)), embed() { const m = @js($video).match(/(?:v=|youtu\.be\/|embed\/)([\w-]{6,})/); return m ? `https://www.youtube.com/embed/${m[1]}?autoplay=1&rel=0` : @js($video); } }">
    <div class="banner-round relative mx-auto aspect-[2/1] max-w-5xl bg-maroon-deep">
        <template x-if="!playing">
            <button type="button" @click="playing = true" class="group absolute inset-0 h-full w-full" aria-label="Play video">
                <img src="{{ $poster }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                <span class="absolute inset-0 bg-gradient-to-t from-maroon-deep/60 to-transparent"></span>
                <span class="absolute left-1/2 top-1/2 grid h-16 w-16 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-red shadow-float transition-transform group-hover:scale-105"><x-ico name="play" :size="24" /></span>
            </button>
        </template>
        <template x-if="playing">
            <div class="absolute inset-0">
                <template x-if="isYouTube"><iframe :src="embed()" class="h-full w-full" allow="autoplay; encrypted-media" allowfullscreen title="Brand video"></iframe></template>
                <template x-if="!isYouTube"><video :src="@js($video)" controls autoplay playsinline class="h-full w-full object-cover"></video></template>
            </div>
        </template>
    </div>
</section>
@endif

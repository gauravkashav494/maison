@if(!empty($g['strip_text']))
@php $simg = \App\Support\Media::url($g['strip_image'] ?? null); @endphp
<section class="mt-[30px]">
    <div class="banner-round relative mx-auto flex w-[calc(100%-2rem)] min-h-[5.5rem] items-center justify-center bg-cream-dark px-6 py-4 sm:px-10 lg:aspect-[1152/118] lg:min-h-0 lg:w-[80%] lg:max-w-[1152px] lg:py-0">
        @if($simg)<img src="{{ $simg }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy"><div class="absolute inset-0 bg-cream/80"></div>@endif
        <p class="relative flex items-center gap-4 text-center font-serif text-base font-semibold text-maroon sm:text-lg lg:text-2xl"><x-ico name="diamond" :size="18" class="hidden shrink-0 text-gold sm:block" /><span>{{ $g['strip_text'] }}</span><x-ico name="diamond" :size="18" class="hidden shrink-0 text-gold sm:block" /></p>
    </div>
</section>
@endif

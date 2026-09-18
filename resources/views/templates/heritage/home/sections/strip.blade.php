@if(!empty($g['strip_text']))
@php $simg = \App\Support\Media::url($g['strip_image'] ?? null); @endphp
<section class="h-container pt-6">
    <div class="banner-round relative flex min-h-[5.5rem] items-center justify-center bg-cream-dark px-6 py-4 sm:px-10 lg:mx-12">
        @if($simg)<img src="{{ $simg }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy"><div class="absolute inset-0 bg-cream/80"></div>@endif
        <p class="relative flex items-center gap-4 text-center font-serif text-base font-semibold text-maroon sm:text-lg lg:text-2xl"><x-ico name="diamond" :size="18" class="hidden shrink-0 text-gold sm:block" /><span>{{ $g['strip_text'] }}</span><x-ico name="diamond" :size="18" class="hidden shrink-0 text-gold sm:block" /></p>
    </div>
</section>
@endif

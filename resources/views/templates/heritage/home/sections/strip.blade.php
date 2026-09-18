@if(!empty($g['strip_text']))
<section class="h-container pt-6">
    <div class="banner-round relative flex items-center gap-4 bg-cream-dark px-6 py-4 sm:px-10 lg:mx-12">
        <x-ico name="diamond" :size="18" class="hidden shrink-0 text-gold sm:block" />
        <p class="flex-1 text-center font-serif text-base font-semibold text-maroon sm:text-lg lg:text-xl">{{ $g['strip_text'] }}</p>
        @if(!empty($g['strip_image']))<img src="{{ \App\Support\Media::url($g['strip_image']) }}" alt="" class="hidden h-16 w-24 rounded-lg object-cover lg:block" loading="lazy">@endif
    </div>
</section>
@endif

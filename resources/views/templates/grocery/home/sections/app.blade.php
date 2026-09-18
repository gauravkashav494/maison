@php $s = tsetting('site'); @endphp
@if(!empty($g['app_heading']))
<section class="g-container section">
    <div class="grid overflow-hidden rounded-2xl bg-leaf text-white lg:grid-cols-2">
        <div class="p-6 sm:p-10 lg:p-14">
            <p class="inline-block rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-wider">App exclusive</p>
            <h2 class="mt-3 text-2xl font-extrabold leading-tight sm:text-3xl">{{ $g['app_heading'] }}</h2>
            <p class="mt-2 max-w-md text-sm text-white/85 sm:text-base">{{ $g['app_text'] ?? '' }}</p>
            <div class="mt-6 flex flex-wrap gap-2">
                <a href="{{ $s['app_store_url'] ?? '#' }}" class="btn bg-white text-ink hover:bg-paper"><x-ico name="apple" :size="18" /> App Store</a>
                <a href="{{ $s['play_store_url'] ?? '#' }}" class="btn bg-white text-ink hover:bg-paper"><x-ico name="play" :size="16" /> Google Play</a>
            </div>
        </div>
        @if(!empty($g['app_image']))<div class="relative hidden min-h-[16rem] lg:block"><img src="{{ \App\Support\Media::url($g['app_image']) }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy"></div>@endif
    </div>
</section>
@endif

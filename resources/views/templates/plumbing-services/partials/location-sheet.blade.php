{{-- Service-area picker bottom sheet (choice is remembered on this device) --}}
@php $areas = $navAreas ?? collect(); @endphp
<div x-data="{ q: '' }" x-show="$store.ui.locationOpen" x-cloak>
    <div class="overlay" x-show="$store.ui.locationOpen" x-transition.opacity @click="$store.ui.locationOpen = false"></div>
    <div class="sheet inset-x-0 bottom-0 mx-auto flex max-h-[85dvh] max-w-lg flex-col rounded-t-3xl lg:bottom-auto lg:top-1/2 lg:-translate-y-1/2 lg:rounded-3xl" x-show="$store.ui.locationOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full lg:translate-y-0 lg:opacity-0" x-transition:enter-end="translate-y-0 lg:opacity-100" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0 lg:opacity-100" x-transition:leave-end="translate-y-full lg:translate-y-0 lg:opacity-0" x-trap.noscroll="$store.ui.locationOpen" role="dialog" aria-modal="true" aria-label="Choose your area">
        <div class="sheet-handle lg:hidden"></div>
        <div class="flex items-center justify-between px-5 pt-4">
            <div><p class="font-display text-lg font-extrabold">Where do you need a plumber?</p><p class="text-xs text-slate">Pick your city to see response times and local pages.</p></div>
            <button type="button" @click="$store.ui.locationOpen = false" class="icon-btn" aria-label="Close"><x-ico name="close" :size="20" /></button>
        </div>
        <div class="px-4 pt-3">
            <label class="flex h-11 items-center gap-2 rounded-full bg-canvas px-4 ring-1 ring-line"><x-ico name="search" :size="16" class="text-primary" /><input type="search" x-model="q" placeholder="Search city or locality" class="min-w-0 flex-1 bg-transparent text-sm focus:outline-none" aria-label="Search city"></label>
        </div>
        <div class="safe-bottom flex-1 space-y-2 overflow-y-auto p-4">
            @foreach($areas as $a)
                <button type="button" x-show="!q || @js(Str::lower($a->name.' '.$a->state.' '.implode(' ', $a->localities ?? []))).includes(q.toLowerCase())" @click="$store.area.set(@js(['name' => $a->name, 'slug' => $a->slug, 'response_time' => $a->response_time]))" class="option w-full" :class="$store.area.current?.slug === @js($a->slug) && 'option-active'">
                    <span class="svc-ico h-10 w-10"><x-ico name="map-pin" :size="18" /></span>
                    <span class="flex-1"><span class="block text-sm font-bold">{{ $a->name }}</span><span class="block text-xs text-slate">{{ $a->state }}@if($a->response_time) · Emergency response {{ $a->response_time }}@endif</span></span>
                    <x-ico name="check" :size="18" class="text-primary" x-show="$store.area.current?.slug === '{{ $a->slug }}'" x-cloak />
                </button>
            @endforeach
            <p class="pt-2 text-center text-xs text-slate">Not listed? <a href="{{ $biz['phone_href'] }}" class="font-semibold text-primary">Call us</a> — we may still be able to help.</p>
        </div>
    </div>
</div>
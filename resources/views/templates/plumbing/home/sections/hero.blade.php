{{-- Split hero: headline + CTAs + trust stats on the left, photo with two category tiles on the right --}}
@php $p = tsetting('site'); $stats = array_values(array_filter($g['hero_stats'] ?? [], fn ($s) => ! empty($s['value']))); $tiles = array_values(array_filter($g['hero_tiles'] ?? [], fn ($t) => ! empty($t['title']))); $img = \App\Support\Media::url($g['hero_image'] ?? null); @endphp
<section class="relative overflow-hidden bg-white">
    <div class="absolute inset-y-0 right-0 hidden w-1/2 bg-sky lg:block" aria-hidden="true"></div>
    <div class="p-container relative grid gap-8 py-8 lg:grid-cols-12 lg:gap-10 lg:py-14">
        <div class="flex flex-col justify-center lg:col-span-6">
            @if(!empty($g['hero_eyebrow']))<p class="inline-flex w-max items-center gap-2 rounded-full bg-sky px-3 py-1 text-xs font-bold uppercase tracking-wider text-primary"><x-ico name="droplet" :size="12" :stroke="2.4" /> {{ $g['hero_eyebrow'] }}</p>@endif
            <h1 class="mt-4 font-display text-[2rem] font-extrabold leading-[1.1] text-deep sm:text-[2.6rem] lg:text-[3.1rem]">{{ $g['hero_heading'] ?? 'Everything you need for every plumbing job' }}</h1>
            @if(!empty($g['hero_text']))<p class="mt-4 max-w-xl text-base leading-relaxed text-slate lg:text-lg">{{ $g['hero_text'] }}</p>@endif
            <div class="mt-6 flex flex-wrap gap-3">
                @if(!empty($g['hero_primary_label']))<a href="{{ $g['hero_primary_url'] ?? '/shop' }}" class="btn btn-primary btn-lg">{{ $g['hero_primary_label'] }} <x-ico name="arrow-right" :size="18" /></a>@endif
                @if(!empty($g['hero_secondary_label']))<a href="{{ $g['hero_secondary_url'] ?? '#categories' }}" class="btn btn-outline btn-lg">{{ $g['hero_secondary_label'] }}</a>@endif
            </div>
            @if($stats)
                <dl class="mt-8 grid max-w-md grid-cols-3 gap-4 border-t border-line pt-6">
                    @foreach($stats as $s)<div><dt class="font-display text-xl font-extrabold text-deep lg:text-2xl">{{ $s['value'] }}</dt><dd class="text-xs text-slate lg:text-sm">{{ $s['label'] ?? '' }}</dd></div>@endforeach
                </dl>
            @endif
            <ul class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-xs font-semibold text-slate">
                <li class="flex items-center gap-1.5"><x-ico name="truck" :size="15" class="text-primary" /> {{ $p['delivery_promise'] ?? 'Pan-India delivery' }}</li>
                <li class="flex items-center gap-1.5"><x-ico name="receipt" :size="15" class="text-primary" /> GST invoice</li>
                <li class="flex items-center gap-1.5"><x-ico name="wallet" :size="15" class="text-primary" /> COD available</li>
            </ul>
        </div>

        <div class="lg:col-span-6">
            <div class="grid grid-cols-3 grid-rows-2 gap-3 lg:h-[30rem]">
                <div class="relative col-span-2 row-span-2 overflow-hidden rounded-2xl bg-sky-dark">
                    @if($img)<img src="{{ $img }}" alt="{{ $g['hero_image_alt'] ?? '' }}" class="img-cover" fetchpriority="high">@endif
                    <span class="absolute left-4 top-4 rounded-lg bg-white/95 px-3 py-1.5 text-xs font-bold text-deep shadow-sm">Genuine products · Manufacturer warranty</span>
                </div>
                @foreach(array_slice($tiles, 0, 2) as $t)
                    <a href="{{ $t['url'] ?? '/shop' }}" class="cat-card group relative aspect-square overflow-hidden lg:aspect-auto">
                        @if(!empty($t['image']))<img src="{{ \App\Support\Media::url($t['image']) }}" alt="" class="img-cover" loading="lazy">@endif
                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-deep/90 to-transparent p-3 text-white"><span class="block text-sm font-bold leading-tight">{{ $t['title'] }}</span>@if(!empty($t['text']))<span class="block text-[0.6875rem] text-white/80">{{ $t['text'] }}</span>@endif</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

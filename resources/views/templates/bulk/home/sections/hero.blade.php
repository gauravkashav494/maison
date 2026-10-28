{{-- Compact hero: headline + three "how it works" steps; the order form sits directly below it --}}
@php $p = tsetting('site'); $steps = array_values(array_filter($g['hero_steps'] ?? [], fn ($s) => ! empty($s['title']))); @endphp
<section class="relative overflow-hidden bg-deep text-white">
    <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-primary/40 blur-3xl" aria-hidden="true"></div>
    <div class="p-container relative grid gap-8 py-8 lg:grid-cols-12 lg:items-center lg:gap-10 lg:py-12">
        <div class="lg:col-span-6">
            @if(!empty($g['hero_eyebrow']))<p class="inline-flex w-max items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-accent"><x-ico name="box" :size="13" :stroke="2.2" /> {{ $g['hero_eyebrow'] }}</p>@endif
            <h1 class="mt-4 font-display text-[1.9rem] font-extrabold leading-[1.1] sm:text-[2.4rem] lg:text-[2.8rem]">{{ $g['hero_heading'] ?? 'Order everything you need in one go' }}</h1>
            @if(!empty($g['hero_text']))<p class="mt-4 max-w-xl text-base leading-relaxed text-white/80">{{ $g['hero_text'] }}</p>@endif
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="#order" class="btn btn-accent btn-lg">{{ $g['hero_primary_label'] ?? 'Start your order' }} <x-ico name="arrow-right" :size="18" /></a>
                @if(!empty($g['hero_secondary_label']))<a href="{{ $g['hero_secondary_url'] ?? '/shop' }}" class="btn btn-lg border border-white/30 text-white hover:bg-white/10">{{ $g['hero_secondary_label'] }}</a>@endif
            </div>
            <ul class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-xs font-semibold text-white/80">
                <li class="flex items-center gap-1.5"><x-ico name="truck" :size="15" class="text-accent" /> {{ $p['delivery_promise'] ?? 'Fast dispatch' }}</li>
                <li class="flex items-center gap-1.5"><x-ico name="receipt" :size="15" class="text-accent" /> GST invoice</li>
                <li class="flex items-center gap-1.5"><x-ico name="wallet" :size="15" class="text-accent" /> COD available</li>
            </ul>
        </div>

        @if($steps)
            <ol class="grid gap-3 sm:grid-cols-3 lg:col-span-6 lg:grid-cols-1">
                @foreach(array_slice($steps, 0, 3) as $s)
                    <li class="flex gap-4 rounded-2xl bg-white/[0.07] p-4 ring-1 ring-white/10 lg:items-center">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-accent font-display text-lg font-extrabold text-ink">{{ $loop->iteration }}</span>
                        <span><span class="block font-bold">{{ $s['title'] }}</span>@if(!empty($s['text']))<span class="mt-0.5 block text-sm text-white/70">{{ $s['text'] }}</span>@endif</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>

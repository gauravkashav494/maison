{{-- Two promotional banners side by side --}}
@php $promos = array_values(array_filter($g['promos'] ?? [], fn ($x) => ! empty($x['heading']))); @endphp
@if($promos)
<section class="p-container section pt-0">
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach(array_slice($promos, 0, 2) as $x)
            @php $tone = $x['tone'] ?? 'blue'; $bg = ['blue' => 'bg-primary text-white', 'orange' => 'bg-accent text-ink', 'light' => 'bg-sky text-ink'][$tone] ?? 'bg-primary text-white'; $btn = $tone === 'blue' ? 'btn-light' : ($tone === 'orange' ? 'btn-deep' : 'btn-primary'); $muted = $tone === 'blue' ? 'text-white/80' : 'text-ink/70'; @endphp
            <a href="{{ $x['url'] ?? '/shop' }}" class="group grid min-h-[13rem] grid-cols-[1.2fr_1fr] overflow-hidden rounded-2xl {{ $bg }}">
                <div class="flex flex-col justify-center p-5 lg:p-8">
                    @if(!empty($x['eyebrow']))<p class="text-[0.6875rem] font-bold uppercase tracking-wider {{ $muted }}">{{ $x['eyebrow'] }}</p>@endif
                    <h3 class="mt-1.5 font-display text-lg font-bold leading-tight lg:text-2xl">{{ $x['heading'] }}</h3>
                    @if(!empty($x['text']))<p class="mt-2 hidden text-sm {{ $muted }} sm:block">{{ $x['text'] }}</p>@endif
                    @if(!empty($x['cta_label']))<span class="btn {{ $btn }} btn-sm mt-4 w-max">{{ $x['cta_label'] }} <x-ico name="arrow-right" :size="14" /></span>@endif
                </div>
                <div class="relative overflow-hidden">@if(!empty($x['image']))<img src="{{ \App\Support\Media::url($x['image']) }}" alt="" class="img-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">@endif</div>
            </a>
        @endforeach
    </div>
</section>
@endif

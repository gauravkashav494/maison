@php $tiles = array_values(array_filter($g['promo_tiles'] ?? [], fn ($t) => ! empty($t['title']))); @endphp
@if($tiles)
<section class="g-container section">
    <div class="grid gap-3 sm:grid-cols-3">
        @foreach($tiles as $t)
            <a href="{{ $t['url'] ?? '#' }}" class="group relative flex min-h-[8.5rem] items-center overflow-hidden rounded-2xl p-5" style="background-color: {{ $t['color'] ?? '#fff4d6' }}">
                <div class="relative z-10 max-w-[60%]">
                    <p class="text-lg font-extrabold leading-tight">{{ $t['title'] }}</p>
                    @if(!empty($t['text']))<p class="mt-1 text-sm text-slate">{{ $t['text'] }}</p>@endif
                    <span class="section-link mt-3">{{ $t['cta_label'] ?? 'Shop now' }} <x-ico name="arrow-right" :size="14" /></span>
                </div>
                @if(!empty($t['image']))<img src="{{ \App\Support\Media::url($t['image']) }}" alt="" class="absolute -right-4 bottom-0 top-0 w-[45%] rounded-l-2xl object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">@endif
            </a>
        @endforeach
    </div>
</section>
@endif

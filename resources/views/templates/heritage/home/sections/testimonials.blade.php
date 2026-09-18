@php $items = array_values(array_filter($g['testimonials'] ?? [], fn ($t) => ! empty($t['text']))); $img = \App\Support\Media::url($g['testimonials_image'] ?? null); @endphp
@if($items)
<section class="h-container py-10 lg:py-[60px]">
    <h2 class="title-c lg:leading-none">{{ $g['testimonials_heading'] ?? 'Happy customers' }}</h2>
    <div class="mt-6 grid gap-[15px] lg:mt-[35px] {{ $img ? 'lg:grid-cols-[299px_1fr]' : '' }}">
        @if($img)<div class="banner-round relative aspect-[3/4] max-h-[26rem] bg-cream-dark lg:aspect-[299/532] lg:max-h-none"><img src="{{ $img }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy"><span class="absolute bottom-3 left-3 rounded-full bg-white/90 px-3 py-1 text-[0.6875rem] font-semibold text-maroon">Real kitchens, real reviews</span></div>@endif
        <div class="grid content-start gap-2.5 sm:grid-cols-2 {{ $img ? 'lg:grid-cols-[555fr_429fr]' : 'lg:grid-cols-3' }}">
            @foreach(array_slice($items, 0, 6) as $t)
                <figure class="review-card lg:min-h-[170px]">
                    <p class="sr-only">{{ $t['rating'] ?? 5 }} out of 5 stars</p>
                    <blockquote class="mt-1">{{ $t['text'] }}</blockquote>
                    <b>{{ implode(', ', array_filter([$t['name'] ?? null, $t['location'] ?? null])) }}{{ !empty($t['product']) ? ' – '.$t['product'] : '' }}</b>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@php $items = array_values(array_filter($g['testimonials'] ?? [], fn ($t) => ! empty($t['text']))); $img = \App\Support\Media::url($g['testimonials_image'] ?? null); @endphp
@if($items)
<section class="h-container section !py-8 lg:!py-10">
    <h2 class="title-c">{{ $g['testimonials_heading'] ?? 'Happy customers' }}</h2>
    <div class="mt-6 grid gap-4 lg:mt-8 lg:grid-cols-12">
        @if($img)<div class="banner-round relative aspect-[3/4] max-h-[26rem] bg-cream-dark lg:col-span-3 lg:max-h-none"><img src="{{ $img }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy"><span class="absolute bottom-3 left-3 rounded-full bg-white/90 px-3 py-1 text-[0.6875rem] font-semibold text-maroon">Real kitchens, real reviews</span></div>@endif
        <div class="grid gap-4 sm:grid-cols-2 {{ $img ? 'lg:col-span-9' : 'lg:col-span-12 lg:grid-cols-3' }}">
            @foreach(array_slice($items, 0, 6) as $t)
                <figure class="review-card">
                    <p class="text-star" aria-label="{{ $t['rating'] ?? 5 }} stars">{{ str_repeat('★', (int) ($t['rating'] ?? 5)) }}</p>
                    <blockquote class="mt-1">{{ $t['text'] }}</blockquote>
                    <b>{{ implode(', ', array_filter([$t['name'] ?? null, $t['location'] ?? null])) }}{{ !empty($t['product']) ? ' – '.$t['product'] : '' }}</b>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

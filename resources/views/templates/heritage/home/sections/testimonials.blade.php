@php $items = array_values(array_filter($g['testimonials'] ?? [], fn ($t) => ! empty($t['text']))); @endphp
@if($items)
<section class="border-y border-line bg-warm">
    <div class="h-container section">
        <x-section-head :eyebrow="$g['testimonials_eyebrow'] ?? 'Customer stories'" :title="$g['testimonials_heading'] ?? 'Loved in kitchens across India'" center />
        <div class="grid gap-4 md:grid-cols-3">
            @foreach($items as $t)
                <figure class="card relative flex flex-col p-6">
                    <x-ico name="quote" :size="28" :stroke="1.2" class="text-gold" />
                    <x-rating :value="(int) ($t['rating'] ?? 5)" :size="14" class="mt-3" />
                    <blockquote class="mt-3 flex-1 font-serif text-[1.05rem] leading-relaxed text-ink">“{{ $t['text'] }}”</blockquote>
                    <figcaption class="mt-5 flex items-center gap-3 border-t border-line-soft pt-4">
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-cream font-serif text-base font-semibold text-red">{{ Str::upper(Str::substr($t['name'] ?? 'C', 0, 1)) }}</span>
                        <span><span class="block text-sm font-semibold">{{ $t['name'] ?? '' }}</span><span class="block text-xs text-muted">{{ implode(' · ', array_filter([$t['location'] ?? null, !empty($t['product']) ? 'Bought '.$t['product'] : null])) }}</span></span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

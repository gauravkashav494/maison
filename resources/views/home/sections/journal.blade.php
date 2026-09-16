@php $lead = $posts->first(); $rest = $posts->slice(1); @endphp
<section class="container-luxe py-20 lg:py-28">
    <x-section-header :eyebrow="$home['journal_eyebrow'] ?? null" :title="$home['journal_heading'] ?? 'The Journal'" cta="Read the journal" :cta-url="route('journal.index')" />

    <div class="mt-12 grid gap-12 lg:grid-cols-12 lg:gap-10">
        <article class="group lg:col-span-7">
            <a href="{{ $lead->url }}" class="block">
                <div class="img-reveal relative aspect-[4/5] bg-sand sm:aspect-[5/4]" x-data x-intersect.once.threshold.25="$el.classList.add('is-visible')">
                    <div class="img-reveal-clip"><img src="{{ $lead->image_url }}" alt="{{ $lead->title }}" loading="lazy" decoding="async" class="img-cover img-zoom absolute inset-0"></div>
                </div>
                <div x-data x-intersect.once="$el.querySelectorAll('.reveal').forEach(e => e.classList.add('is-visible'))">
                    <div class="reveal mt-6 flex items-center gap-3 text-[0.625rem] uppercase tracking-[0.22em] text-taupe">
                        <span>{{ $lead->category }}</span><span class="h-px w-6 bg-taupe/40"></span><span>{{ $lead->published_at?->format('F Y') }}</span>
                        <span class="ml-auto">{{ $lead->read_time }}</span>
                    </div>
                    <h3 class="reveal mt-3 font-serif text-4xl leading-[1.05] lg:text-5xl" style="--reveal-delay:.05s"><span class="link-underline">{{ $lead->title }}</span></h3>
                    <p class="reveal mt-4 max-w-xl text-[0.9375rem] leading-relaxed text-smoke" style="--reveal-delay:.05s">{{ $lead->excerpt }}</p>
                </div>
            </a>
        </article>

        <div class="flex flex-col divide-y divide-ink/10 lg:col-span-5 lg:pl-4">
            @foreach($rest as $i => $s)
                <article class="group py-8 first:pt-0">
                    <a href="{{ $s->url }}" class="grid grid-cols-[38%_1fr] gap-5 sm:grid-cols-[32%_1fr] lg:grid-cols-[40%_1fr]">
                        <div class="img-reveal relative aspect-[3/4] bg-sand" style="--reveal-delay: {{ $loop->index * 0.1 }}s" x-data x-intersect.once.threshold.25="$el.classList.add('is-visible')">
                            <div class="img-reveal-clip"><img src="{{ $s->image_url }}" alt="{{ $s->title }}" loading="lazy" decoding="async" class="img-cover img-zoom absolute inset-0"></div>
                        </div>
                        <div class="reveal flex flex-col justify-center" style="--reveal-delay: {{ $loop->index * 0.1 }}s" x-data x-intersect.once="$el.classList.add('is-visible')">
                            <p class="text-[0.625rem] uppercase tracking-[0.22em] text-taupe">{{ $s->category }} · {{ $s->read_time }}</p>
                            <h3 class="mt-3 font-serif text-2xl leading-tight lg:text-3xl"><span class="link-underline">{{ $s->title }}</span></h3>
                            <p class="mt-3 text-sm leading-relaxed text-smoke">{{ $s->excerpt }}</p>
                            <span class="eyebrow mt-5 text-ink">Read more →</span>
                        </div>
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>

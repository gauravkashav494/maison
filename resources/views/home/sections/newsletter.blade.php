<section class="bg-sand">
    <div class="container-luxe py-20 lg:py-28">
        <div class="mx-auto max-w-2xl text-center" x-data x-intersect.once="$el.querySelectorAll('.reveal').forEach(e => e.classList.add('is-visible'))">
            @if(!empty($home['newsletter_eyebrow']))<p class="reveal eyebrow text-taupe">{{ $home['newsletter_eyebrow'] }}</p>@endif
            <h2 class="reveal display-md mt-5 text-balance" style="--reveal-delay:.1s">{!! emph($home['newsletter_heading'] ?? 'Newsletter') !!}</h2>
            <p class="reveal mx-auto mt-5 max-w-md text-[0.9375rem] leading-relaxed text-smoke" style="--reveal-delay:.2s">{{ $home['newsletter_text'] ?? '' }}</p>
            <div class="reveal" style="--reveal-delay:.3s">
                @include('partials.newsletter-form', ['light' => false, 'source' => 'homepage', 'class' => 'mx-auto mt-10 max-w-md text-left'])
            </div>
        </div>
    </div>
</section>

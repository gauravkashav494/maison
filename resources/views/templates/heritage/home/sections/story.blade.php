@if(!empty($g['story_heading']))
@php $paragraphs = preg_split('/\n\s*\n/', trim((string) ($g['story_text'] ?? ''))); $stats = array_filter($g['story_stats'] ?? [], fn ($s) => ! empty($s['value'])); @endphp
<section class="h-container section">
    <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-16">
        <div class="relative lg:col-span-6">
            <div class="gold-frame overflow-hidden rounded-2xl">
                <div class="aspect-[4/5] bg-cream-dark">@if(!empty($g['story_image']))<img src="{{ \App\Support\Media::url($g['story_image']) }}" alt="" class="img-cover" loading="lazy">@endif</div>
            </div>
            @if(!empty($g['story_image_secondary']))
                <div class="absolute -bottom-6 -right-3 hidden w-2/5 overflow-hidden rounded-xl border-4 border-cream shadow-float sm:block lg:-right-8"><div class="aspect-[4/3]"><img src="{{ \App\Support\Media::url($g['story_image_secondary']) }}" alt="" class="img-cover" loading="lazy"></div></div>
            @endif
        </div>
        <div class="lg:col-span-6">
            @if(!empty($g['story_eyebrow']))<p class="eyebrow">{{ $g['story_eyebrow'] }}</p>@endif
            <h2 class="display mt-3 text-3xl text-maroon sm:text-4xl">{{ $g['story_heading'] }}</h2>
            <div class="mt-5 space-y-4 text-[0.9375rem] leading-relaxed text-muted">@foreach($paragraphs as $p)<p>{{ $p }}</p>@endforeach</div>
            @if($stats)
                <dl class="mt-8 grid grid-cols-3 gap-4 border-y border-line py-6">
                    @foreach($stats as $s)<div><dt class="font-serif text-2xl font-semibold text-red sm:text-3xl">{{ $s['value'] }}</dt><dd class="mt-1 text-xs uppercase tracking-wider text-muted">{{ $s['label'] ?? '' }}</dd></div>@endforeach
                </dl>
            @endif
            @if(!empty($g['story_cta_label']))<a href="{{ $g['story_cta_url'] ?? '/about' }}" class="btn btn-outline mt-8">{{ $g['story_cta_label'] }} <x-ico name="arrow-right" :size="16" /></a>@endif
        </div>
    </div>
</section>
@endif

{{-- How it works: four numbered steps --}}
@php $steps = collect($g['how_steps'] ?? [])->filter(fn ($s) => ! empty($s['title'])); @endphp
@if($steps->isNotEmpty())
<section class="section">
    <div class="ps-container">
        <x-section-head :title="$g['how_heading'] ?? 'Getting a plumber is easy'" eyebrow="How it works" />
        <div class="relative lg:grid lg:grid-cols-4 lg:gap-8">
            <div class="absolute left-[1.35rem] top-2 bottom-8 w-px bg-line lg:hidden"></div>
            <div class="absolute left-[12%] right-[12%] top-[1.35rem] hidden h-px bg-line lg:block"></div>
            @foreach($steps as $i => $s)
                <x-step :n="$i + 1" :title="$s['title']" :text="$s['text'] ?? null" :icon="$s['icon'] ?? null" class="relative" />
            @endforeach
        </div>
        <div class="mt-2 flex flex-wrap gap-2 lg:mt-8"><a href="{{ route('booking.create') }}" class="btn btn-primary">Book a plumber</a><a href="{{ route('quote.create') }}" class="btn btn-soft">Request a quote</a></div>
    </div>
</section>
@endif
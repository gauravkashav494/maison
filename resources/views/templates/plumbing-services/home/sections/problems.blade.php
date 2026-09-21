{{-- Problem-first discovery: pick what is wrong, land on the right service --}}
@php $problems = collect($g['problems'] ?? [])->filter(fn ($p) => ! empty($p['label'])); @endphp
@if($problems->isNotEmpty())
<section class="band-sky section" id="problems">
    <div class="ps-container">
        <x-section-head :title="$g['problems_heading'] ?? 'What’s the problem?'" :sub="$g['problems_sub'] ?? null" />
        <div class="grid grid-cols-2 gap-2.5 md:grid-cols-3 lg:grid-cols-4 lg:gap-4">
            @foreach($problems as $p)<x-problem-tile :problem="$p" class="lg:p-4 lg:text-sm" />@endforeach
        </div>
        <p class="mt-4 text-center text-sm text-slate lg:mt-6">Something else? <a href="{{ route('booking.create') }}" class="font-bold text-primary">Describe it and book</a> or <a href="{{ template()->contact()['phone_href'] }}" class="font-bold text-primary">call us</a>.</p>
    </div>
</section>
@endif
{{-- Quote request block --}}
@php $site = tsetting('site'); @endphp
<section class="ps-container section pt-0 lg:pt-0">
    <div class="card grid gap-5 overflow-hidden p-5 lg:grid-cols-[1fr_auto] lg:items-center lg:p-10">
        <div class="flex gap-4">
            <span class="svc-ico svc-ico-accent hidden h-14 w-14 sm:grid"><x-ico name="file" :size="26" /></span>
            <div>
                <h2 class="font-display text-xl font-extrabold lg:text-3xl">{{ $site['quote_heading'] ?? 'Need a quote?' }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm text-slate lg:text-base">{{ $site['quote_text'] ?? '' }}</p>
            </div>
        </div>
        <a href="{{ route('quote.create') }}" class="btn btn-accent btn-lg">Request a quote</a>
    </div>
</section>
{{-- Bulk / professional buying band: links to the contact form with the Bulk quote subject pre-selected --}}
@php $p = tsetting('site'); $points = array_values(array_filter((array) ($p['bulk_points'] ?? []))); @endphp
@if(!empty($p['bulk_heading']))
<section class="p-container section">
    <div class="relative overflow-hidden rounded-2xl bg-deep text-white">
        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-bright/30 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-24 left-1/3 h-64 w-64 rounded-full bg-accent/20 blur-3xl" aria-hidden="true"></div>
        <div class="relative grid gap-8 p-6 lg:grid-cols-12 lg:items-center lg:p-10">
            <div class="lg:col-span-7">
                <p class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-accent"><x-ico name="building" :size="14" /> For contractors, plumbers & builders</p>
                <h2 class="mt-3 font-display text-2xl font-bold leading-tight lg:text-3xl">{{ $p['bulk_heading'] }}</h2>
                @if(!empty($p['bulk_text']))<p class="mt-3 max-w-xl text-sm leading-relaxed text-white/80 lg:text-base">{{ $p['bulk_text'] }}</p>@endif
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ $p['bulk_cta_url'] ?? '/contact?subject=Bulk+quote' }}" class="btn btn-accent btn-lg">{{ $p['bulk_cta_label'] ?? 'Request bulk quote' }} <x-ico name="arrow-right" :size="18" /></a>
                    @if(!empty($p['whatsapp_number']))<a href="https://wa.me/{{ preg_replace('/\D/', '', $p['whatsapp_number']) }}?text={{ rawurlencode('Hi, I have a bulk plumbing requirement.') }}" target="_blank" rel="noopener" class="btn btn-lg border border-white/30 text-white hover:bg-white/10"><x-ico name="whatsapp" :size="18" /> WhatsApp the list</a>@endif
                </div>
            </div>
            @if($points)
                <ul class="grid gap-3 sm:grid-cols-2 lg:col-span-5">
                    @foreach($points as $pt)<li class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 text-sm font-semibold"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-accent text-ink"><x-ico name="check" :size="14" :stroke="3" /></span>{{ $pt }}</li>@endforeach
                </ul>
            @endif
        </div>
    </div>
</section>
@endif

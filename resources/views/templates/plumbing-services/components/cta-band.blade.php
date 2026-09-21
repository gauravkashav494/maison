{{-- Final conversion band: Book / Call / WhatsApp on a deep-blue gradient --}}
@props(['heading' => null, 'text' => null, 'image' => null])
@php $biz = template()->contact(); $g = tsetting('home'); $img = $image ?? ($g['cta_image'] ?? null); @endphp
<section {{ $attributes->class('ps-container') }}>
    <div class="band-deep relative overflow-hidden rounded-3xl">
        @if($img)<img src="{{ $img }}" alt="" loading="lazy" decoding="async" class="absolute inset-y-0 right-0 hidden h-full w-2/5 object-cover opacity-90 lg:block" style="mask-image: linear-gradient(to right, transparent, black 35%); -webkit-mask-image: linear-gradient(to right, transparent, black 35%)">@endif
        <div class="absolute -left-20 -top-20 h-64 w-64 rounded-full bg-bright/40 blur-3xl"></div>
        <div class="relative max-w-xl px-6 py-8 lg:px-12 lg:py-16">
            @if($biz['response'])<p class="eyebrow text-white/80">{{ $biz['response'] }}</p>@endif
            <h2 class="mt-2 font-display text-2xl font-extrabold leading-tight text-balance lg:text-4xl">{{ $heading ?? $g['cta_heading'] ?? 'Need a plumber?' }}</h2>
            <p class="mt-2 text-sm text-white/85 lg:text-base">{{ $text ?? $g['cta_text'] ?? '' }}</p>
            <div class="mt-5 flex flex-wrap gap-2.5">
                <a href="{{ route('booking.create') }}" class="btn btn-accent btn-lg">Book a plumber</a>
                <a href="{{ $biz['phone_href'] }}" class="btn btn-light btn-lg"><x-ico name="phone" :size="18" /> Call now</a>
                @if($biz['whatsapp'])<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-lg"><x-ico name="whatsapp" :size="18" /> WhatsApp</a>@endif
            </div>
        </div>
    </div>
</section>
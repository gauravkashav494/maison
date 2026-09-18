@php
    $h = tsetting('site');
    $g = tsetting('home');
    $col1 = $menus['footer_company'] ?? collect();
    $col2 = $menus['footer_help'] ?? collect();
    $socials = ['facebook' => $site['social_facebook'] ?? null, 'instagram' => $site['social_instagram'] ?? null, 'youtube' => $site['social_youtube'] ?? null, 'pinterest' => $site['social_pinterest'] ?? null];
    $email = $h['support_email'] ?? ($site['contact_email'] ?? null);
    $phone = $h['support_phone'] ?? ($site['contact_phone'] ?? null);
@endphp
<footer class="mt-14 bg-red text-cream">
    {{-- Newsletter row --}}
    <div class="h-container flex flex-col gap-4 border-b border-cream/15 py-7 lg:flex-row lg:items-center lg:gap-10" x-data="newsletter('heritage-footer')">
        <h2 class="shrink-0 font-serif text-2xl font-semibold text-gold-light">{{ $g['newsletter_heading'] ?? 'Sign Up To Get Updates' }}</h2>
        <form @submit.prevent="submit()" class="flex flex-1 flex-col gap-2 sm:flex-row" x-show="!done">
            <input x-model="email" type="email" required placeholder="Enter Your Email Address..." class="h-12 flex-1 rounded-full border border-transparent bg-red-dark px-5 text-cream placeholder:text-cream/60 focus:border-gold focus:outline-none" aria-label="Email address">
            <button type="submit" :disabled="busy" class="btn btn-gold h-12 rounded-full px-7">Subscribe</button>
        </form>
        <p x-show="done" x-cloak class="flex items-center gap-2 font-medium text-gold-light"><x-ico name="check" :size="18" /> Welcome — your first recipe is on its way.</p>
        <p x-show="error" x-cloak class="text-sm text-gold-light" x-text="error"></p>
    </div>

    <div class="h-container grid gap-10 py-10 md:grid-cols-2 lg:grid-cols-12">
        {{-- Logo, social, copyright --}}
        <div class="lg:col-span-4">
            <a href="{{ route('home') }}" class="inline-grid h-[4.5rem] w-[4.5rem] place-items-center rounded-full border-2 border-gold bg-cream text-red">
                <span class="flex flex-col items-center leading-none"><x-ico name="diamond" :size="14" :stroke="1.8" /><span class="mt-1 font-serif text-[0.6rem] font-semibold uppercase tracking-[0.1em]">{{ Str::substr($h['logo_primary'] ?? 'Annapurna', 0, 9) }}</span><span class="text-[0.5rem] uppercase tracking-[0.2em] text-gold">{{ $h['logo_sub'] ?? '' }}</span></span>
            </a>
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-cream/80">{{ $h['footer_blurb'] ?? ($site['footer_blurb'] ?? '') }}</p>
            <div class="mt-5 flex gap-2.5">
                @foreach($socials as $icon => $url)@if($url)<a href="{{ $url }}" target="_blank" rel="noreferrer" class="grid h-9 w-9 place-items-center rounded-full bg-cream text-red hover:bg-gold-light" aria-label="{{ $icon }}"><x-ico :name="$icon" :size="17" /></a>@endif @endforeach
            </div>
            <p class="mt-5 text-xs text-cream/70">Copyright {{ date('Y') }} {{ $site['name'] ?? config('app.name') }}.<br>All rights reserved.</p>
            @if(!empty($h['certifications']))<ul class="mt-4 flex flex-wrap gap-1.5">@foreach($h['certifications'] as $cert)<li class="rounded-full border border-gold/60 px-2.5 py-0.5 text-[0.6875rem] text-gold-light">{{ $cert }}</li>@endforeach</ul>@endif
        </div>

        {{-- Two link columns --}}
        <div class="lg:col-span-2">
            <ul class="space-y-2.5 text-sm">@foreach($col1 as $item)<li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-gold-light"></span><a href="{{ $item->href }}" class="hover:text-gold-light">{{ $item->label }}</a></li>@endforeach</ul>
        </div>
        <div class="lg:col-span-2">
            <ul class="space-y-2.5 text-sm">@foreach($col2 as $item)<li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-gold-light"></span><a href="{{ $item->href }}" class="hover:text-gold-light">{{ $item->label }}</a></li>@endforeach @foreach($menus['legal'] ?? [] as $item)<li class="flex gap-2"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-gold-light"></span><a href="{{ $item->href }}" class="hover:text-gold-light">{{ $item->label }}</a></li>@endforeach</ul>
        </div>

        {{-- Get in touch --}}
        <div class="space-y-3 text-sm lg:col-span-4">
            <div><p class="font-semibold text-gold-light">Get in Touch:</p>@if($email)<p><a href="mailto:{{ $email }}" class="hover:text-gold-light">{{ $email }}</a> <span class="text-cream/60">(Order related queries)</span></p>@endif @if(!empty($h['whatsapp_number']))<p><a href="https://wa.me/{{ preg_replace('/\D/', '', $h['whatsapp_number']) }}" target="_blank" rel="noopener" class="hover:text-gold-light">WhatsApp us</a></p>@endif</div>
            @if(!empty($h['support_toll_free']) || $phone)<div><p class="font-semibold text-gold-light">Toll Free:</p><p>{{ $h['support_toll_free'] ?? $phone }}</p></div>@endif
            @if(!empty($h['support_hours']))<div><p class="font-semibold text-gold-light">Timings:</p><p>{{ $h['support_hours'] }}</p></div>@endif
            @if(!empty($h['gifting_email']))<div><p class="font-semibold text-gold-light">For Corporate / Institutional Gifting:</p><p><a href="mailto:{{ $h['gifting_email'] }}" class="hover:text-gold-light">{{ $h['gifting_email'] }}</a></p></div>@endif
            @if(!empty($site['payment_methods']))<div><p class="font-semibold text-gold-light">We accept:</p><ul class="mt-1 flex flex-wrap gap-1.5">@foreach($site['payment_methods'] as $pm)<li class="rounded border border-cream/30 px-2 py-0.5 text-[0.6875rem] text-cream/85">{{ $pm }}</li>@endforeach</ul></div>@endif
        </div>
    </div>

    {{-- Decorative bottom band (ornamental gold pattern instead of imagery) --}}
    <div class="relative h-16 overflow-hidden bg-maroon-deep">
        <div class="absolute inset-x-0 top-0 h-px bg-gold/60"></div>
        <div class="h-container flex h-full items-center justify-center gap-6 text-gold/70" aria-hidden="true">
            @for($i = 0; $i < 9; $i++)<x-ico name="diamond" :size="10" :stroke="1.4" class="hidden sm:block" />@endfor
            <span class="font-serif text-xs italic tracking-wide text-gold-light">Pure ingredients. Timeless Indian taste.</span>
            @for($i = 0; $i < 9; $i++)<x-ico name="diamond" :size="10" :stroke="1.4" class="hidden sm:block" />@endfor
        </div>
    </div>
</footer>

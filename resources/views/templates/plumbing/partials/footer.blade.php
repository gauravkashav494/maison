@php
    $p = tsetting('site');
    $cols = [
        'Shop' => $menus['footer_shop'] ?? collect(),
        'Customer support' => $menus['footer_support'] ?? collect(),
        'Company' => $menus['footer_company'] ?? collect(),
        'Policies' => $menus['legal'] ?? collect(),
    ];
    $socials = ['instagram' => $site['social_instagram'] ?? null, 'facebook' => $site['social_facebook'] ?? null, 'youtube' => $site['social_youtube'] ?? null, 'linkedin' => $site['social_linkedin'] ?? null];
    $phone = $p['support_phone'] ?? ($site['contact_phone'] ?? null);
    $email = $p['support_email'] ?? ($site['contact_email'] ?? null);
@endphp
<footer class="mt-10 bg-deep text-white">
    {{-- Newsletter strip --}}
    <div class="border-b border-white/10">
        <div class="p-container flex flex-col gap-4 py-7 lg:flex-row lg:items-center lg:justify-between" x-data="newsletter('plumbing-footer')">
            <div class="flex items-center gap-4">
                <span class="hidden h-12 w-12 place-items-center rounded-xl bg-white/10 sm:grid"><x-ico name="mail" :size="22" /></span>
                <div>
                    <p class="font-display text-lg font-bold">{{ $p['newsletter_heading'] ?? 'Get offers & new arrivals' }}</p>
                    <p class="text-sm text-white/70">{{ $p['newsletter_text'] ?? '' }}</p>
                </div>
            </div>
            <form @submit.prevent="submit()" class="flex w-full max-w-md gap-2" x-show="!done">
                <input x-model="email" type="email" required placeholder="Your email address" class="h-12 min-w-0 flex-1 rounded-xl border border-white/20 bg-white/10 px-4 text-white placeholder:text-white/50 focus:border-accent focus:outline-none" aria-label="Email address">
                <button type="submit" :disabled="busy" class="btn btn-accent h-12">Subscribe</button>
            </form>
            <p x-show="done" x-cloak class="flex items-center gap-2 text-sm font-semibold text-accent"><x-ico name="check" :size="18" /> You’re on the list.</p>
            <p x-show="error" x-cloak class="text-sm text-red-300" x-text="error"></p>
        </div>
    </div>

    <div class="p-container grid gap-10 py-10 lg:grid-cols-12 lg:gap-8">
        {{-- Brand + contact --}}
        <div class="lg:col-span-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-white text-deep"><x-ico name="droplet" :size="22" :stroke="2" /></span>
                <span class="font-display text-xl font-extrabold tracking-tight">{{ $p['logo_primary'] ?? 'Plumb' }}<span class="text-accent">{{ $p['logo_accent'] ?? 'Kart' }}</span></span>
            </a>
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-white/70">{{ $p['footer_blurb'] ?? ($site['footer_blurb'] ?? '') }}</p>
            <ul class="mt-5 space-y-2.5 text-sm">
                @if($phone)<li class="flex items-center gap-2.5"><x-ico name="phone" :size="16" class="text-accent" /> <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="font-semibold hover:text-accent">{{ $phone }}</a> <span class="text-xs text-white/50">{{ $p['support_hours'] ?? '' }}</span></li>@endif
                @if($email)<li class="flex items-center gap-2.5"><x-ico name="mail" :size="16" class="text-accent" /> <a href="mailto:{{ $email }}" class="font-semibold hover:text-accent">{{ $email }}</a></li>@endif
                @if(!empty($p['whatsapp_number']))<li class="flex items-center gap-2.5"><x-ico name="whatsapp" :size="16" class="text-accent" /> <a href="https://wa.me/{{ preg_replace('/\D/', '', $p['whatsapp_number']) }}" target="_blank" rel="noopener" class="font-semibold hover:text-accent">WhatsApp us</a></li>@endif
            </ul>
            <div class="mt-5 flex gap-2">
                @foreach($socials as $icon => $url)
                    @if($url)<a href="{{ $url }}" target="_blank" rel="noreferrer" class="grid h-9 w-9 place-items-center rounded-lg bg-white/10 text-white hover:bg-accent hover:text-ink" aria-label="{{ $icon }}"><x-ico :name="$icon" :size="16" /></a>@endif
                @endforeach
            </div>
        </div>

        {{-- Link columns: accordions on phones, open columns from sm up --}}
        @foreach($cols as $title => $items)
            <div class="max-sm:border-t max-sm:border-white/10 lg:col-span-2" x-data="{ o: false }">
                <button type="button" @click="o = !o" class="flex w-full items-center justify-between py-3.5 text-left sm:pointer-events-none sm:py-0" :aria-expanded="o"><span class="text-xs font-bold uppercase tracking-wider text-accent">{{ $title }}</span><x-ico name="chevron-down" :size="16" class="text-white/60 transition-transform sm:hidden" ::class="o && 'rotate-180'" /></button>
                <ul class="space-y-2.5 pb-4 text-sm text-white/80 sm:mt-4 sm:pb-0" :class="o ? '' : 'max-sm:hidden'">
                    @foreach($items as $item)<li><a href="{{ $item->href }}" @if($item->opens_in_new_tab) target="_blank" rel="noopener" @endif class="hover:text-white">{{ $item->label }}</a></li>@endforeach
                </ul>
            </div>
        @endforeach
    </div>

    {{-- Bottom bar: payments + copyright --}}
    <div class="border-t border-white/10">
        <div class="p-container flex flex-col gap-3 py-5 text-xs text-white/60 sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ date('Y') }} {{ $site['name'] ?? config('app.name') }}. All rights reserved. Prices inclusive of GST.</p>
            @if(!empty($site['payment_methods']))
                <ul class="flex flex-wrap gap-1.5" aria-label="Accepted payment methods">
                    @foreach($site['payment_methods'] as $pm)<li class="rounded-md border border-white/20 px-2 py-1 text-[0.6875rem] font-bold text-white/80">{{ $pm }}</li>@endforeach
                </ul>
            @endif
        </div>
    </div>
</footer>

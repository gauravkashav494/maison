@php
    $g = tsetting('site');
    $h = tsetting('home');
    $cols = [
        'Categories' => $menus['footer_categories'] ?? collect(),
        'Help' => $menus['footer_help'] ?? collect(),
        'Company' => $menus['footer_company'] ?? collect(),
    ];
    $socials = ['instagram' => $site['social_instagram'] ?? null, 'facebook' => $site['social_facebook'] ?? null, 'youtube' => $site['social_youtube'] ?? null, 'pinterest' => $site['social_pinterest'] ?? null];
    $phone = $g['support_phone'] ?? ($site['contact_phone'] ?? null);
@endphp
<footer class="mt-8 border-t border-line bg-white">
    {{-- Newsletter --}}
    <div class="g-container py-8 lg:py-10">
        <div class="flex flex-col gap-5 rounded-2xl bg-leaf-light p-6 lg:flex-row lg:items-center lg:justify-between lg:p-8" x-data="newsletter('grocery-footer')">
            <div class="max-w-lg">
                <h2 class="text-xl font-extrabold lg:text-2xl">{{ $h['newsletter_heading'] ?? 'Get deals in your inbox' }}</h2>
                <p class="mt-1 text-sm text-slate">{{ $h['newsletter_text'] ?? 'Weekly offers and new arrivals. No spam.' }}</p>
            </div>
            <form @submit.prevent="submit()" class="flex w-full max-w-md flex-col gap-2 sm:flex-row" x-show="!done">
                <input x-model="email" type="email" required placeholder="Your email address" class="field h-12 flex-1" aria-label="Email address">
                <button type="submit" :disabled="busy" class="btn btn-primary h-12">Subscribe</button>
            </form>
            <p x-show="done" x-cloak class="flex items-center gap-2 text-sm font-bold text-leaf-dark"><x-ico name="check" :size="18" /> You’re in! Watch your inbox for offers.</p>
            <p x-show="error" x-cloak class="text-sm text-nonveg" x-text="error"></p>
        </div>
    </div>

    <div class="g-container grid gap-8 border-t border-line py-8 max-sm:gap-0 sm:grid-cols-2 lg:grid-cols-12 lg:py-10">
        {{-- Brand --}}
        <div class="max-sm:pb-6 lg:col-span-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-leaf text-white"><x-ico name="leaf" :size="20" :stroke="2.2" /></span>
                <span class="text-xl font-extrabold tracking-tight"><span class="text-leaf-dark">{{ $g['logo_primary'] ?? 'Maison' }}</span><span class="text-saffron">{{ $g['logo_accent'] ?? 'Fresh' }}</span></span>
            </a>
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-slate">{{ $g['footer_blurb'] ?? ($site['footer_blurb'] ?? '') }}</p>
            <ul class="mt-5 space-y-2 text-sm">
                @if($phone)<li class="flex items-center gap-2"><x-ico name="phone" :size="16" class="text-leaf" /> <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="font-semibold hover:text-leaf">{{ $phone }}</a> <span class="text-xs text-mist">{{ $g['support_hours'] ?? '' }}</span></li>@endif
                @if(!empty($site['contact_email']))<li class="flex items-center gap-2"><x-ico name="mail" :size="16" class="text-leaf" /> <a href="mailto:{{ $site['contact_email'] }}" class="font-semibold hover:text-leaf">{{ $site['contact_email'] }}</a></li>@endif
                @if(!empty($g['whatsapp_number']))<li class="flex items-center gap-2"><x-ico name="whatsapp" :size="16" class="text-leaf" /> <a href="https://wa.me/{{ preg_replace('/\D/', '', $g['whatsapp_number']) }}" target="_blank" rel="noopener" class="font-semibold hover:text-leaf">Chat on WhatsApp</a></li>@endif
            </ul>
            <div class="mt-5 flex gap-2">
                @foreach($socials as $icon => $url)
                    @if($url)<a href="{{ $url }}" target="_blank" rel="noreferrer" class="grid h-9 w-9 place-items-center rounded-lg border border-line text-slate hover:border-leaf hover:text-leaf" aria-label="{{ $icon }}"><x-ico :name="$icon" :size="16" /></a>@endif
                @endforeach
            </div>
        </div>

        {{-- Link columns: accordions on phones (app-style), open columns from sm up --}}
        @foreach($cols as $title => $items)
            <div class="max-sm:border-t max-sm:border-line lg:col-span-2" x-data="{ o: false }">
                <button type="button" @click="o = !o" class="flex w-full items-center justify-between py-3.5 text-left sm:pointer-events-none sm:py-0" :aria-expanded="o"><span class="text-xs font-extrabold uppercase tracking-wider text-ink">{{ $title }}</span><x-ico name="chevron-down" :size="16" class="text-slate transition-transform sm:hidden" ::class="o && 'rotate-180'" /></button>
                <ul class="space-y-2.5 pb-4 text-sm text-slate sm:mt-4 sm:pb-0" :class="o ? '' : 'max-sm:hidden'">
                    @foreach($items as $item)
                        <li><a href="{{ $item->href }}" @if($item->opens_in_new_tab) target="_blank" rel="noopener" @endif class="hover:text-leaf">{{ $item->label }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        {{-- App + payments --}}
        <div class="max-sm:border-t max-sm:border-line max-sm:pt-6 lg:col-span-2">
            <p class="text-xs font-extrabold uppercase tracking-wider text-ink">{{ $g['app_heading'] ?? 'Get the app' }}</p>
            <p class="mt-3 text-sm text-slate">{{ $g['app_text'] ?? '' }}</p>
            <div class="mt-4 flex flex-col gap-2">
                <a href="{{ $g['app_store_url'] ?? '#' }}" class="btn btn-dark btn-sm justify-start gap-3"><x-ico name="apple" :size="18" /> <span class="flex flex-col items-start leading-none"><span class="text-[0.625rem] font-medium opacity-80">Download on the</span><span class="text-sm">App Store</span></span></a>
                <a href="{{ $g['play_store_url'] ?? '#' }}" class="btn btn-dark btn-sm justify-start gap-3"><x-ico name="play" :size="16" /> <span class="flex flex-col items-start leading-none"><span class="text-[0.625rem] font-medium opacity-80">Get it on</span><span class="text-sm">Google Play</span></span></a>
            </div>
            @if(!empty($site['payment_methods']))
                <p class="mt-6 text-xs font-extrabold uppercase tracking-wider text-ink">We accept</p>
                <ul class="mt-3 flex flex-wrap gap-1.5">
                    @foreach($site['payment_methods'] as $pm)<li class="rounded-md border border-line px-2 py-1 text-[0.6875rem] font-bold text-slate">{{ $pm }}</li>@endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="border-t border-line">
        <div class="g-container flex flex-col gap-3 py-5 text-xs text-slate sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ date('Y') }} {{ $site['name'] ?? config('app.name') }}. All rights reserved.</p>
            <ul class="flex flex-wrap gap-x-5 gap-y-1">
                @foreach($menus['legal'] ?? [] as $item)<li><a href="{{ $item->href }}" class="hover:text-leaf">{{ $item->label }}</a></li>@endforeach
            </ul>
        </div>
    </div>
</footer>

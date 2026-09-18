@php
    $h = tsetting('site');
    $g = tsetting('home');
    $cols = ['Shop' => $menus['footer_shop'] ?? collect(), 'Customer support' => $menus['footer_help'] ?? collect(), 'About' => $menus['footer_company'] ?? collect()];
    $socials = ['instagram' => $site['social_instagram'] ?? null, 'facebook' => $site['social_facebook'] ?? null, 'youtube' => $site['social_youtube'] ?? null, 'pinterest' => $site['social_pinterest'] ?? null];
    $phone = $h['support_phone'] ?? ($site['contact_phone'] ?? null);
@endphp
<footer class="mt-12">
    {{-- Newsletter --}}
    <div class="h-container pb-10">
        <div class="gold-frame relative overflow-hidden rounded-2xl bg-maroon px-6 py-10 text-cream sm:px-10 lg:px-14" x-data="newsletter('heritage-footer')">
            <div class="grid items-center gap-6 lg:grid-cols-2">
                <div>
                    <p class="eyebrow text-gold-light">{{ $g['newsletter_eyebrow'] ?? 'Stay in touch' }}</p>
                    <h2 class="mt-3 font-serif text-2xl font-semibold leading-tight sm:text-3xl">{{ $g['newsletter_heading'] ?? 'Recipes, harvest news and offers' }}</h2>
                    <p class="mt-2 text-sm text-cream/80">{{ $g['newsletter_text'] ?? '' }}</p>
                </div>
                <div>
                    <form @submit.prevent="submit()" class="flex flex-col gap-2 sm:flex-row" x-show="!done">
                        <input x-model="email" type="email" required placeholder="Your email address" class="field h-12 flex-1 border-transparent bg-white/95" aria-label="Email address">
                        <button type="submit" :disabled="busy" class="btn btn-gold h-12">Subscribe</button>
                    </form>
                    <p x-show="done" x-cloak class="flex items-center gap-2 font-medium text-gold-light"><x-ico name="check" :size="18" /> Welcome to the family — your first recipe is on its way.</p>
                    <p x-show="error" x-cloak class="mt-2 text-sm text-gold-light" x-text="error"></p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-maroon-deep text-cream">
        <div class="h-container grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="grid h-10 w-10 place-items-center rounded-full border border-gold text-gold"><x-ico name="diamond" :size="16" :stroke="1.8" /></span>
                    <span class="font-serif text-2xl leading-none"><span class="font-semibold text-cream">{{ $h['logo_primary'] ?? 'Annapurna' }}</span> <span class="italic text-gold">{{ $h['logo_accent'] ?? 'Organics' }}</span></span>
                </a>
                <p class="mt-5 max-w-sm text-sm leading-relaxed text-cream/75">{{ $h['footer_blurb'] ?? ($site['footer_blurb'] ?? '') }}</p>
                @if(!empty($h['certifications']))
                    <ul class="mt-5 flex flex-wrap gap-2">@foreach($h['certifications'] as $cert)<li class="badge badge-outline !normal-case !tracking-normal text-gold-light">{{ $cert }}</li>@endforeach</ul>
                @endif
                <ul class="mt-6 space-y-2 text-sm text-cream/85">
                    @if($phone)<li class="flex items-center gap-2"><x-ico name="phone" :size="16" class="text-gold" /><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="hover:text-gold-light">{{ $phone }}</a><span class="text-xs text-cream/50">{{ $h['support_hours'] ?? '' }}</span></li>@endif
                    @if(!empty($site['contact_email']))<li class="flex items-center gap-2"><x-ico name="mail" :size="16" class="text-gold" /><a href="mailto:{{ $site['contact_email'] }}" class="hover:text-gold-light">{{ $site['contact_email'] }}</a></li>@endif
                    @if(!empty($h['whatsapp_number']))<li class="flex items-center gap-2"><x-ico name="whatsapp" :size="16" class="text-gold" /><a href="https://wa.me/{{ preg_replace('/\D/', '', $h['whatsapp_number']) }}" target="_blank" rel="noopener" class="hover:text-gold-light">Chat on WhatsApp</a></li>@endif
                </ul>
                <div class="mt-6 flex gap-2">
                    @foreach($socials as $icon => $url)@if($url)<a href="{{ $url }}" target="_blank" rel="noreferrer" class="grid h-9 w-9 place-items-center rounded-full border border-cream/20 text-cream/80 hover:border-gold hover:text-gold" aria-label="{{ $icon }}"><x-ico :name="$icon" :size="16" /></a>@endif @endforeach
                </div>
            </div>
            @foreach($cols as $title => $items)
                <div class="lg:col-span-2">
                    <p class="font-serif text-lg text-gold-light">{{ $title }}</p>
                    <ul class="mt-4 space-y-2.5 text-sm text-cream/80">
                        @foreach($items as $item)<li><a href="{{ $item->href }}" @if($item->opens_in_new_tab) target="_blank" rel="noopener" @endif class="hover:text-gold-light">{{ $item->label }}</a></li>@endforeach
                    </ul>
                </div>
            @endforeach
            <div class="lg:col-span-2">
                <p class="font-serif text-lg text-gold-light">We accept</p>
                @if(!empty($site['payment_methods']))<ul class="mt-4 flex flex-wrap gap-1.5">@foreach($site['payment_methods'] as $pm)<li class="rounded border border-cream/20 px-2 py-1 text-[0.6875rem] font-semibold text-cream/80">{{ $pm }}</li>@endforeach</ul>@endif
                <p class="mt-6 font-serif text-lg text-gold-light">Delivery</p>
                <p class="mt-2 text-sm text-cream/75">{{ $h['delivery_note'] ?? 'Pan-India delivery' }}. Free above {{ money((int) setting('site.free_shipping_threshold', 999)) }}.</p>
            </div>
        </div>
        <div class="border-t border-cream/10">
            <div class="h-container flex flex-col gap-3 py-5 text-xs text-cream/60 sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ date('Y') }} {{ $site['name'] ?? config('app.name') }}. All rights reserved.</p>
                <ul class="flex flex-wrap gap-x-5 gap-y-1">@foreach($menus['legal'] ?? [] as $item)<li><a href="{{ $item->href }}" class="hover:text-gold-light">{{ $item->label }}</a></li>@endforeach</ul>
            </div>
        </div>
    </div>
</footer>

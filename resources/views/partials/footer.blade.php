@php
    $columns = [
        ['title' => 'Shop', 'items' => $menus['footer_shop']],
        ['title' => 'Collections', 'items' => $menus['footer_collections']],
        ['title' => 'About', 'items' => $menus['footer_about']],
        ['title' => 'Customer Service', 'items' => $menus['footer_service']],
    ];
    $socials = [
        ['instagram', $site['social_instagram'] ?? null, 'Instagram'],
        ['pinterest', $site['social_pinterest'] ?? null, 'Pinterest'],
        ['facebook', $site['social_facebook'] ?? null, 'Facebook'],
        ['youtube', $site['social_youtube'] ?? null, 'YouTube'],
    ];
@endphp
<footer class="bg-ink text-ivory">
    <div class="container-luxe">
        <div class="grid gap-10 border-b border-ivory/10 py-14 lg:grid-cols-12 lg:gap-16">
            <div class="lg:col-span-5">
                <p class="font-serif text-3xl tracking-[0.12em]">{{ $site['logo_primary'] ?? 'MAISON' }} <span class="italic font-light">{{ $site['logo_accent'] ?? 'Élan' }}</span></p>
                @if(!empty($site['footer_blurb']))<p class="mt-4 max-w-sm text-sm leading-relaxed text-ivory/60">{{ $site['footer_blurb'] }}</p>@endif
                <div class="mt-6 flex items-center gap-5 text-ivory/70">
                    @foreach($socials as [$icon, $url, $label])
                        @if($url)<a href="{{ $url }}" aria-label="{{ $label }}" class="hover:text-ivory" target="_blank" rel="noreferrer"><x-ico :name="$icon" :size="17" :stroke="1.5" /></a>@endif
                    @endforeach
                </div>
            </div>
            <div class="lg:col-span-6 lg:col-start-7">
                <p class="eyebrow text-ivory/60">Newsletter</p>
                <p class="mt-3 font-serif text-2xl">{{ $site['footer_newsletter_heading'] ?? 'Early access, private sales and notes from the atelier.' }}</p>
                @include('partials.newsletter-form', ['light' => true, 'source' => 'footer', 'class' => 'mt-5 max-w-md'])
            </div>
        </div>

        <div class="grid grid-cols-2 gap-10 py-14 md:grid-cols-4">
            @foreach($columns as $col)
                <div>
                    <p class="eyebrow mb-5 text-ivory/50">{{ $col['title'] }}</p>
                    <ul class="space-y-2.5">
                        @foreach($col['items'] as $l)
                            <li><a href="{{ $l->href }}" class="link-underline text-[0.8125rem] text-ivory/80 hover:text-ivory">{{ $l->label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="flex flex-col gap-6 border-t border-ivory/10 py-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="text-[0.75rem] text-ivory/60">
                <p>Client care <a href="mailto:{{ $site['contact_email'] ?? '' }}" class="text-ivory/90 hover:text-ivory">{{ $site['contact_email'] ?? '' }}</a> · {{ $site['contact_phone'] ?? '' }}</p>
                <p class="mt-1">{{ $site['contact_hours'] ?? '' }}</p>
            </div>
            <div class="flex flex-wrap gap-2" aria-label="Accepted payment methods">
                @foreach($site['payment_methods'] ?? [] as $m)
                    <span class="grid h-7 min-w-11 place-items-center border border-ivory/20 px-2 text-[0.5625rem] font-semibold uppercase tracking-wider text-ivory/70">{{ $m }}</span>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-4 border-t border-ivory/10 py-6 text-[0.6875rem] text-ivory/50 md:flex-row md:items-center md:justify-between">
            <p>© {{ date('Y') }} {{ $site['name'] ?? config('app.name') }}. All rights reserved.</p>
            <ul class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach($menus['legal'] as $l)
                    <li><a href="{{ $l->href }}" class="hover:text-ivory">{{ $l->label }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="h-16 lg:hidden"></div>
</footer>

{{-- Store footer: trade updates sign-up, about, shop links, help links, the trade desk, and the policies. --}}
@php
    $storeName = setting('site.name', config('app.name'));
    // Site settings → Contact & social: the social profiles filled in, and the payment badges.
    $socials = array_filter([
        'instagram' => setting('site.social_instagram'), 'facebook' => setting('site.social_facebook'),
        'youtube' => setting('site.social_youtube'), 'pinterest' => setting('site.social_pinterest'),
        'linkedin' => setting('site.social_linkedin'),
    ]);
    $payments = array_filter((array) setting('site.payment_methods', []), 'filled');
@endphp

<footer class="bk-foot mt-12">
    {{-- Trade updates: price-list and new-stock emails, saved to Marketing → Subscribers --}}
    @module('subscribers')
    <div class="border-b border-white/10">
        <div class="bk-wrap flex flex-col gap-3 py-5 lg:flex-row lg:items-center lg:justify-between" x-data="newsletter('bulk-footer')">
            <div class="max-w-lg">
                <h3 class="!mb-1">{{ tsetting('site.newsletter_heading', 'Get trade price updates') }}</h3>
                <p>{{ tsetting('site.newsletter_text', 'New stock and price-list changes, straight to your inbox. No spam.') }}</p>
            </div>
            <div class="w-full max-w-md">
                <form @submit.prevent="submit()" class="flex flex-col gap-2 sm:flex-row" x-show="!done" novalidate>
                    <input x-model="email" type="email" placeholder="Work email address" class="bk-input flex-1 !py-2 text-sm" aria-label="Email address" autocomplete="email">
                    <button type="submit" :disabled="busy" class="bk-btn bk-btn-accent !px-4 !py-2">Subscribe</button>
                </form>
                <p x-show="done" x-cloak class="flex items-center gap-2 font-semibold text-white"><x-heroicon-o-check-circle class="size-5" aria-hidden="true" /> You’re subscribed. Watch your inbox for trade updates.</p>
                <p x-show="error" x-cloak class="mt-2 text-sm text-red-300" x-text="error"></p>
            </div>
        </div>
    </div>
    @endmodule

    <div class="bk-wrap grid gap-6 py-7 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <x-store-logo size="h-10" :on-dark="true">
            <p class="bk-logo"><span class="bk-logo-mark"><x-heroicon-o-cube aria-hidden="true" /></span><span>{{ $storeName }}</span></p>
            </x-store-logo>
            <p class="mt-3 leading-relaxed">{{ tsetting('site.footer_blurb', '') }}</p>
            @if($socials)
                <div class="mt-4 flex items-center gap-2">
                    @foreach($socials as $icon => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($icon) }}" class="grid size-8 place-items-center rounded-md border border-white/15 hover:border-white/40 hover:text-white"><x-ico :name="$icon" :size="16" /></a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Content → Navigation → "Footer — Shop", like the Help column beside it. No links, no column. --}}
        @if(collect($menus['footer_shop'] ?? [])->isNotEmpty())
            <div data-menu-area="footer_shop">
                <h3>Shop</h3>
                <ul class="space-y-1.5">
                    @foreach($menus['footer_shop'] as $item)
                        <li><a href="{{ $item->url }}" @if($item->opens_in_new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div data-menu-area="footer">
            <h3>Help</h3>
            <ul class="space-y-1.5">
                @foreach($menus['footer'] ?? [] as $item)
                    <li><a href="{{ $item->url }}" @if($item->opens_in_new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3>Trade desk</h3>
            <ul class="space-y-1.5">
                @if($phone = store_phone())
                    <li class="flex items-center gap-2"><x-heroicon-o-phone class="size-4" aria-hidden="true" /><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a></li>
                @endif
                @if($email = store_email())
                    <li class="flex items-center gap-2"><x-heroicon-o-envelope class="size-4" aria-hidden="true" /><a href="mailto:{{ $email }}">{{ $email }}</a></li>
                @endif
                @if($hours = tsetting('site.support_hours'))
                    <li class="flex items-center gap-2"><x-heroicon-o-clock class="size-4" aria-hidden="true" /><span>{{ $hours }}</span></li>
                @endif
            </ul>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="bk-wrap flex flex-wrap items-center justify-between gap-3 py-3 text-xs">
            <span>© {{ date('Y') }} {{ $storeName }}</span>
            @if($payments)
                <ul class="flex flex-wrap gap-1.5" aria-label="Accepted payment methods">
                    @foreach($payments as $pm)<li class="rounded border border-white/20 px-2 py-0.5 text-[0.6875rem] font-semibold">{{ $pm }}</li>@endforeach
                </ul>
            @endif
            <span class="flex flex-wrap gap-4" data-menu-area="legal">
                @foreach($menus['legal'] ?? [] as $item)
                    <a href="{{ $item->url }}" @if($item->opens_in_new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>

{{-- Store footer: trade updates sign-up, about, shop links, help links, the trade desk, and the policies. --}}
@php $storeName = setting('site.name', config('app.name')); @endphp

<footer class="bk-foot mt-16">
    {{-- Trade updates: price-list and new-stock emails, saved to Marketing → Subscribers --}}
    <div class="border-b border-white/10">
        <div class="bk-wrap flex flex-col gap-4 py-8 lg:flex-row lg:items-center lg:justify-between" x-data="newsletter('bulk-footer')">
            <div class="max-w-lg">
                <h3 class="!mb-1">{{ tsetting('site.newsletter_heading', 'Get trade price updates') }}</h3>
                <p>{{ tsetting('site.newsletter_text', 'New stock and price-list changes, straight to your inbox. No spam.') }}</p>
            </div>
            <div class="w-full max-w-md">
                <form @submit.prevent="submit()" class="flex flex-col gap-2 sm:flex-row" x-show="!done" novalidate>
                    <input x-model="email" type="email" placeholder="Work email address" class="bk-input flex-1" aria-label="Email address" autocomplete="email">
                    <button type="submit" :disabled="busy" class="bk-btn bk-btn-accent px-5 py-2.5">Subscribe</button>
                </form>
                <p x-show="done" x-cloak class="flex items-center gap-2 font-semibold text-white"><x-heroicon-o-check-circle class="size-5" aria-hidden="true" /> You’re subscribed. Watch your inbox for trade updates.</p>
                <p x-show="error" x-cloak class="mt-2 text-sm text-red-300" x-text="error"></p>
            </div>
        </div>
    </div>

    <div class="bk-wrap grid gap-8 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="bk-logo"><span class="bk-logo-mark"><x-heroicon-o-cube aria-hidden="true" /></span><span>{{ $storeName }}</span></p>
            <p class="mt-4 leading-relaxed">{{ tsetting('site.footer_blurb', '') }}</p>
        </div>

        <div>
            <h3>Shop</h3>
            <ul class="space-y-2">
                <li><a href="{{ url('/#bulk-order') }}">Bulk order</a></li>
                <li><a href="{{ route('shop.index') }}">All products</a></li>
                @foreach(($navCategories ?? collect())->take(5) as $cat)
                    <li><a href="{{ $cat->url }}">{{ $cat->name }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3>Help</h3>
            <ul class="space-y-2">
                @foreach($menus['footer'] ?? [] as $item)
                    <li><a href="{{ $item->url }}">{{ $item->label }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3>Trade desk</h3>
            <ul class="space-y-2">
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
        <div class="bk-wrap flex flex-wrap items-center justify-between gap-3 py-5 text-xs">
            <span>© {{ date('Y') }} {{ $storeName }}</span>
            <span class="flex flex-wrap gap-4">
                @foreach($menus['legal'] ?? [] as $item)
                    <a href="{{ $item->url }}">{{ $item->label }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>

{{-- Fixed bottom navigation (phones/tablets): Home · Services · Book (raised) · Contact · Account --}}
@php
    $tabs = [
        ['Home', route('home'), request()->routeIs('home'), 'home'],
        ['Services', route('services.index'), request()->routeIs('services.*') || request()->routeIs('areas.*'), 'grid'],
        ['Book', route('booking.create'), request()->routeIs('booking.*'), 'calendar'],
        ['Contact', '/contact', request()->is('contact'), 'headset'],
        ['Account', auth()->check() ? route('account.index') : route('login'), request()->is('account*') || request()->is('login') || request()->is('register') || request()->is('bookings'), 'user'],
    ];
@endphp
<nav class="tab-bar lg:hidden" aria-label="Primary">
    <ul class="grid grid-cols-5">
        @foreach($tabs as $i => [$label, $url, $active, $icon])
            <li>
                @if($i === 2)
                    <a href="{{ $url }}" class="tab-item" aria-label="Book a plumber" @if($active) aria-current="page" @endif>
                        <span class="tab-fab"><x-ico name="calendar" :size="24" :stroke="2" /></span>
                        <span class="text-primary">{{ $label }}</span>
                    </a>
                @else
                    <a href="{{ $url }}" class="tab-item {{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>
                        <span class="tab-ico"><x-ico :name="$icon" :size="20" /></span>
                        <span>{{ $label }}</span>
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
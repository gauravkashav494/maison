{{-- Client dashboard of a staff account with no module yet: a welcome and who can give access. --}}
<x-filament-widgets::widget>
    <div class="fi-welcome">
        <p class="fi-welcome-kicker">Welcome</p>
        <h2 class="fi-welcome-title">Hello, {{ $firstName }}</h2>
        <p class="fi-welcome-text">
            Your account is ready, but you haven't been given access to any section yet.
            Once you have, the screens you can use will appear in the menu and here on your dashboard.
        </p>
        <p class="fi-welcome-contact">
            @if ($contact)
                Ask your store admin, <strong>{{ $contact->name }}</strong>
                (<a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>), to give you access.
            @else
                Ask your store admin to give you access.
            @endif
        </p>
    </div>
</x-filament-widgets::widget>

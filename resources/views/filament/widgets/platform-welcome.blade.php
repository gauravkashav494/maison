{{-- Top of the platform dashboard: a greeting and the sections this account can open. --}}
<x-filament-widgets::widget>
    <div class="fi-welcome fi-welcome-bar">
        <div>
            <h2 class="fi-welcome-title">Hello, {{ $firstName }}</h2>
            @if ($superAdmin)
                <p class="fi-welcome-text">You are a super admin and have access to every section.</p>
            @elseif ($sections->isNotEmpty())
                <p class="fi-welcome-text">You have access to {{ $sections->pluck('label')->join(', ', ' and ') }}.</p>
            @else
                <p class="fi-welcome-text">
                    Your account is ready, but you haven't been given access to any section yet.
                    @if ($contact)
                        Ask a super admin, <strong>{{ $contact->name }}</strong>
                        (<a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>), to give you access.
                    @else
                        Ask a super admin to give you access.
                    @endif
                </p>
            @endif
        </div>
        @if ($sections->isNotEmpty())
            <div class="fi-welcome-actions">
                @foreach ($sections as $section)
                    <a href="{{ $section['url'] }}" class="fi-welcome-btn">{{ $section['label'] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>

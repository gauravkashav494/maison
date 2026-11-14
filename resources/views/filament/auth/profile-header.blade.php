{{-- Profile page header: who is signed in, their role and the store they work in. --}}
<div class="fi-profile-hero">
    <div class="fi-profile-hero-banner" aria-hidden="true"></div>

    <div class="fi-profile-hero-body">
        <div class="fi-profile-avatar" aria-hidden="true">{{ $initials }}</div>

        <div class="fi-profile-hero-text">
            <h2 class="fi-profile-hero-name">{{ $name }}</h2>
            <p class="fi-profile-hero-email">{{ $email }}</p>

            <div class="fi-profile-chips">
                <span class="fi-profile-chip is-role">
                    <x-filament::icon icon="heroicon-m-shield-check" class="fi-profile-chip-icon" />
                    {{ $role }}
                </span>
                <span class="fi-profile-chip">
                    <x-filament::icon icon="heroicon-m-building-storefront" class="fi-profile-chip-icon" />
                    {{ $workspace }}
                </span>
                <span @class(['fi-profile-chip', 'is-active' => $active, 'is-inactive' => ! $active])>
                    <span class="fi-profile-dot"></span>
                    {{ $active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>

        @if ($memberSince)
            <div class="fi-profile-hero-meta">
                <span class="fi-profile-meta-label">Member since</span>
                <span class="fi-profile-meta-value">{{ $memberSince }}</span>
            </div>
        @endif
    </div>
</div>

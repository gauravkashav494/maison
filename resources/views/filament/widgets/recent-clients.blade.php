<x-filament-widgets::widget>
    <div class="fi-admin-card">
        <div class="fi-admin-card-head">
            <p class="fi-admin-card-title"><x-filament::icon icon="heroicon-o-briefcase" /> Recent clients</p>
            <a href="{{ $indexUrl }}" class="fi-admin-card-link">View all <x-filament::icon icon="heroicon-m-arrow-right" /></a>
        </div>
        @forelse($clients as $client)
            <div class="fi-admin-row">
                <div>
                    <a href="{{ $viewUrl($client) }}" class="fi-admin-row-title fi-admin-row-link">{{ $client->name }}</a>
                    <p class="fi-admin-row-sub">{{ $client->storefront?->name ?? 'No store' }} · {{ $client->plan?->name ?? 'No plan' }} · added {{ $client->created_at?->format('d M Y') }}</p>
                </div>
                <div class="fi-admin-row-meta">
                    <span>{{ $usage($client) }}</span>
                    <span class="fi-admin-pill {{ $client->is_active ? 'is-green' : 'is-red' }}">{{ $client->is_active ? 'Active' : 'Suspended' }}</span>
                </div>
            </div>
        @empty
            <p class="fi-admin-empty">No clients yet.</p>
        @endforelse
    </div>
</x-filament-widgets::widget>

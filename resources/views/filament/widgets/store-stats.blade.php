<x-filament-widgets::widget>
    <div class="fi-admin-stats">
        @foreach($stats as $s)
            <a href="{{ $s['url'] }}" class="fi-admin-stat">
                <div>
                    <p class="fi-admin-stat-label">{{ $s['label'] }}</p>
                    <p class="fi-admin-stat-value">{{ $s['value'] }}</p>
                    <p class="fi-admin-stat-desc">{{ $s['description'] }}</p>
                </div>
                <span class="fi-admin-stat-icon {{ $s['color'] }}">
                    <x-filament::icon :icon="'heroicon-o-'.$s['icon']" />
                </span>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>

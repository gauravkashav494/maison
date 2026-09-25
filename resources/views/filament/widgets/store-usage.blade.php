<x-filament-widgets::widget>
    <div class="fi-usage-grid">
        @foreach($meters as $m)
            <div class="fi-usage">
                <div class="fi-usage-head">
                    <span class="fi-usage-label">{{ $m['label'] }}</span>
                    <span class="fi-usage-value">{{ $m['used'] }} <span>/ {{ $m['total'] }}</span></span>
                </div>
                <div class="fi-usage-bar"><span style="width: {{ $m['percent'] }}%" @class(['is-full' => $m['percent'] >= 100, 'is-high' => $m['percent'] >= 80 && $m['percent'] < 100])></span></div>
                <p class="fi-usage-note">{{ $m['note'] }}</p>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
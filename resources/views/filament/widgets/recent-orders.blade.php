<x-filament-widgets::widget>
    <div class="fi-admin-card">
        <div class="fi-admin-card-head">
            <p class="fi-admin-card-title"><x-filament::icon icon="heroicon-o-shopping-cart" /> Recent orders</p>
            <a href="{{ $indexUrl }}" class="fi-admin-card-link">View all <x-filament::icon icon="heroicon-m-arrow-right" /></a>
        </div>
        @forelse($orders as $order)
            <a href="{{ $viewUrl($order) }}" class="fi-admin-row">
                <div>
                    <p class="fi-admin-row-title">{{ $order->shipping_name ?: $order->email }} <span style="font-weight:500;color:#64748b">· {{ $order->number }}</span></p>
                    <p class="fi-admin-row-sub">{{ $order->email }} · {{ $order->created_at->format('d M Y, H:i') }}</p>
                </div>
                <div class="fi-admin-row-meta">
                    <span>{{ $order->items_count }} {{ Str::plural('item', $order->items_count) }}</span>
                    <strong>{{ money((int) $order->total) }}</strong>
                    <span class="fi-admin-pill {{ $pill($order->status) }}">{{ $order->statusLabel() }}</span>
                </div>
            </a>
        @empty
            <p class="fi-admin-empty">No orders yet — they’ll show up here as soon as customers check out.</p>
        @endforelse
    </div>
</x-filament-widgets::widget>

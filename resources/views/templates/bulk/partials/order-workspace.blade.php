{{--
    The bulk order section, full width: the order form ("Add items"), the paste-a-list tab, and
    "Order again" with the signed-in buyer's recent orders. Loading a past order switches back
    to the form with its rows filled in. $quickIndex and $recentOrders come from
    App\Templates\Bulk\HomeComposer.
--}}
<div class="bk-card p-4 sm:p-6"
     x-data="{ tab: location.hash === '#paste-list' ? 'paste' : 'items' }" @bk-load-order.window="tab = 'items'">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="bk-tabs" role="tablist" aria-label="How to enter your order">
            <button type="button" role="tab" :aria-selected="tab === 'items'" @click="tab = 'items'"><x-heroicon-o-squares-2x2 aria-hidden="true" />Add items</button>
            <button type="button" role="tab" :aria-selected="tab === 'paste'" @click="tab = 'paste'"><x-heroicon-o-clipboard-document-list aria-hidden="true" />Paste a list</button>
            <button type="button" role="tab" :aria-selected="tab === 'again'" @click="tab = 'again'"><x-heroicon-o-arrow-path aria-hidden="true" />Order again</button>
        </div>
        {{-- The order form draws its Category filter here (x-teleport), so it lines up with the tabs. --}}
        <div id="bk-tab-tools" class="w-full sm:w-auto" x-show="tab === 'items'"></div>
    </div>

    <div x-show="tab === 'items'"><x-items-form :index="$quickIndex" /></div>
    <div x-show="tab === 'paste'" x-cloak><x-quick-order :index="$quickIndex" /></div>

    <div x-show="tab === 'again'" x-cloak>
        @auth
            @if($recentOrders)
                <p class="bk-muted text-sm">Load a past order into the form, change what you need, then add it.</p>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($recentOrders as $order)
                        <li class="flex items-center justify-between gap-3 rounded-lg border border-[var(--bk-line)] p-3">
                            <span class="min-w-0 text-sm">
                                <span class="block font-bold">{{ $order['number'] }}</span>
                                <span class="bk-muted block text-xs">{{ $order['date'] }} · {{ $order['lines'] }} {{ \Illuminate\Support\Str::plural('item', $order['lines']) }} · {{ $order['total'] }}@if($order['skipped']) · {{ $order['skipped'] }} without a code @endif</span>
                            </span>
                            @if($order['pad'] !== '')
                                <button type="button" class="bk-btn bk-btn-outline !px-3 !py-1.5 text-xs"
                                        @click="$dispatch('bk-load-order', @js($order['pad']))">Load</button>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('account.orders') }}" class="bk-link mt-4 inline-block text-sm">All my orders →</a>
            @else
                <p class="bk-muted text-sm">Your orders will appear here, ready to repeat in one click.</p>
            @endif
        @else
            <p class="font-extrabold">Ordering regularly?</p>
            <p class="bk-muted mt-1 text-sm">Sign in to see your recent orders here and repeat any of them in one click.</p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <a href="{{ route('login') }}" class="bk-btn bk-btn-primary">Sign in</a>
                <a href="{{ route('register') }}" class="bk-link text-sm">Create an account</a>
            </div>
        @endauth
    </div>
</div>

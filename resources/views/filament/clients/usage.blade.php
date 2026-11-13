@php
    use App\Clients\ClientQuota;

    /** @var \App\Models\Client $client */
    $client = $getRecord();
    $quota = ClientQuota::for($client);

    $meter = fn (string $label, string $icon, string $used, ?string $limit, ?int $percent, string $left) => compact('label', 'icon', 'used', 'limit', 'percent', 'left');

    $cards = [
        $meter('Products', 'heroicon-o-cube', number_format($quota->productCount()),
            $quota->productLimit() === null ? null : number_format($quota->productLimit()),
            $quota->productPercent(),
            $quota->productLimit() === null ? 'No cap' : number_format($quota->productsLeft()).' left'),
        $meter('Product images', 'heroicon-o-photo', number_format($quota->imageCount()),
            $quota->imageLimit() === null ? null : number_format($quota->imageLimit()),
            $quota->imagePercent(),
            $quota->imageLimit() === null ? 'No cap' : number_format($quota->imagesLeft()).' left'),
        $meter('Storage', 'heroicon-o-server-stack', ClientQuota::formatBytes($quota->storageUsed()),
            $quota->storageLimit() === null ? null : ClientQuota::formatBytes($quota->storageLimit()),
            $quota->storagePercent(),
            $quota->storageLimit() === null ? 'No cap' : ClientQuota::formatBytes($quota->storageLeft()).' left'),
    ];

    $staff = $client->users()->count();
    $activeStaff = $client->users()->where('is_active', true)->count();
    $customers = $client->customers()->count();

    $tone = fn (?int $p) => match (true) {
        $p === null => ['bar' => 'bg-gray-300 dark:bg-gray-600', 'text' => 'text-gray-500 dark:text-gray-400'],
        $p >= 100 => ['bar' => 'bg-danger-600 dark:bg-danger-500', 'text' => 'text-danger-600 dark:text-danger-400'],
        $p >= 80 => ['bar' => 'bg-warning-500', 'text' => 'text-warning-600 dark:text-warning-400'],
        default => ['bar' => 'bg-primary-600 dark:bg-primary-500', 'text' => 'text-gray-500 dark:text-gray-400'],
    };
@endphp

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($cards as $card)
        @php($t = $tone($card['percent']))
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-50 dark:bg-white/5">
                    <x-filament::icon :icon="$card['icon']" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $card['used'] }}</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">/ {{ $card['limit'] ?? 'Unlimited' }}</span>
            </div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"
                 role="progressbar" aria-label="{{ $card['label'] }} used" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $card['percent'] ?? 0 }}">
                <div class="h-full rounded-full {{ $t['bar'] }}" style="width: {{ $card['percent'] ?? 0 }}%"></div>
            </div>
            <div class="mt-2 flex items-center justify-between text-xs">
                <span class="{{ $t['text'] }} font-medium">{{ $card['percent'] === null ? 'Unlimited' : $card['percent'].'% used' }}</span>
                <span class="text-gray-500 dark:text-gray-400">{{ $card['left'] }}</span>
            </div>
        </div>
    @endforeach

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">People</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-50 dark:bg-white/5">
                <x-filament::icon icon="heroicon-o-user-group" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
            </span>
        </div>
        <div class="mt-2 grid grid-cols-2 divide-x divide-gray-100 dark:divide-white/10">
            <div>
                <div class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ number_format($staff) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Staff · {{ $activeStaff }} active</div>
            </div>
            <div class="pl-4">
                <div class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ number_format($customers) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Customers</div>
            </div>
        </div>
    </div>
</div>

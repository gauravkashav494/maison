@php
    /** @var \App\Models\Client $client */
    $client = $getRecord();
    $initials = str($client->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    [$statusLabel, $statusColor, $statusIcon] = match (true) {
        $client->trashed() => ['Deleted', 'gray', 'heroicon-m-trash'],
        (bool) $client->is_active => ['Active', 'success', 'heroicon-m-check-circle'],
        default => ['Inactive', 'danger', 'heroicon-m-pause-circle'],
    };
    $facts = [
        ['Store', $client->storefront?->name ?? '—', 'heroicon-o-building-storefront'],
        ['Plan', $client->plan?->name ?? 'No plan', 'heroicon-o-credit-card'],
        ['Client since', $client->created_at?->format('d M Y') ?? '—', 'heroicon-o-calendar-days'],
        ['Last updated', $client->updated_at?->diffForHumans() ?? '—', 'heroicon-o-clock'],
    ];
@endphp

<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-lg font-semibold tracking-wide text-white shadow-sm dark:bg-primary-500">
                {{ $initials ?: '?' }}
            </div>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $client->name }}</h2>
                    <x-filament::badge :color="$statusColor" :icon="$statusIcon" size="sm">{{ $statusLabel }}</x-filament::badge>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-mono" title="Subdomain">{{ $client->subdomain }}.{{ \App\Models\Client::baseDomain() }}</span>
                    @if ($client->domain)
                        <span class="text-gray-300 dark:text-gray-600">•</span>
                        <span>{{ $client->domain }}</span>
                    @endif
                    @if ($client->plan?->price_label)
                        <span class="text-gray-300 dark:text-gray-600">•</span>
                        <span>{{ $client->plan->price_label }}</span>
                    @endif
                </div>
            </div>
        </div>

        @unless ($client->trashed())
            <div class="flex shrink-0 flex-wrap gap-2">
                <x-filament::button tag="a" :href="$client->publicUrl()" target="_blank" color="gray" size="sm" icon="heroicon-m-arrow-top-right-on-square">
                    Storefront
                </x-filament::button>
                <x-filament::button tag="a" :href="$client->loginUrl()" target="_blank" color="gray" size="sm" icon="heroicon-m-arrow-right-end-on-rectangle">
                    Client login
                </x-filament::button>
            </div>
        @endunless
    </div>

    <dl class="grid grid-cols-2 divide-gray-100 border-t border-gray-100 bg-gray-50/60 lg:grid-cols-4 lg:divide-x dark:divide-white/5 dark:border-white/10 dark:bg-white/[0.02]">
        @foreach ($facts as [$label, $value, $icon])
            <div class="flex items-center gap-3 px-6 py-4">
                <x-filament::icon :icon="$icon" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
                <div class="min-w-0">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $value }}</dd>
                </div>
            </div>
        @endforeach
    </dl>
</div>

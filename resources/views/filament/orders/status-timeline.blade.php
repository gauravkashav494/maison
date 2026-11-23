@php
    /**
     * Order status history as a vertical timeline, newest first: a dot per change (the current
     * status filled in), its label, when it happened, who made it and any note.
     *
     * @var \App\Models\Order $order
     */
    $order = $getRecord();
    $entries = array_reverse($order->statusTimeline());

    $tone = fn (string $status) => match ($status) {
        'delivered' => ['dot' => 'bg-emerald-500', 'ring' => 'ring-emerald-100 dark:ring-emerald-500/20', 'text' => 'text-emerald-700 dark:text-emerald-400'],
        'cancelled' => ['dot' => 'bg-rose-500', 'ring' => 'ring-rose-100 dark:ring-rose-500/20', 'text' => 'text-rose-700 dark:text-rose-400'],
        default => ['dot' => 'bg-primary-600', 'ring' => 'ring-primary-100 dark:ring-primary-500/20', 'text' => 'text-gray-950 dark:text-white'],
    };
@endphp

@if($entries === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">No status changes yet.</p>
@else
    <ol class="relative">
        @foreach($entries as $entry)
            @php
                $at = filled($entry['at'] ?? null) ? \Illuminate\Support\Carbon::parse($entry['at'])->timezone(config('app.timezone')) : null;
                $by = filled($entry['by'] ?? null) ? trim(\Illuminate\Support\Str::before($entry['by'], '<')) : null;
                $t = $tone($entry['status']);
                $latest = $loop->first;
            @endphp
            <li class="relative flex gap-4 {{ $loop->last ? '' : 'pb-6' }}">
                {{-- The line joining this step to the one below it. --}}
                @unless($loop->last)
                    <span class="absolute left-[0.4375rem] top-5 -bottom-1 w-px bg-gray-200 dark:bg-white/10" aria-hidden="true"></span>
                @endunless

                <span @class([
                    'relative mt-1 size-3.5 flex-none rounded-full ring-4',
                    $t['dot'].' '.$t['ring'] => $latest,
                    'bg-white border-2 border-gray-300 ring-transparent dark:bg-gray-900 dark:border-white/20' => ! $latest,
                ]) aria-hidden="true"></span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5">
                        <p @class(['text-sm font-semibold', $t['text'] => $latest, 'text-gray-700 dark:text-gray-300' => ! $latest])>
                            {{ $entry['label'] }}
                            @if($latest)<span class="ml-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">Current</span>@endif
                        </p>
                        @if($at)
                            <time datetime="{{ $at->toIso8601String() }}" class="text-xs text-gray-500 dark:text-gray-400" title="{{ $at->format('d M Y, H:i:s') }}">
                                {{ $at->format('d M Y, H:i') }} · {{ $at->diffForHumans() }}
                            </time>
                        @endif
                    </div>
                    @if(filled($entry['note'] ?? null) || $by)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ $entry['note'] ?? '' }}
                            @if($by)<span class="text-gray-400 dark:text-gray-500">{{ filled($entry['note'] ?? null) ? ' — ' : '' }}by {{ $by }}</span>@endif
                        </p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif

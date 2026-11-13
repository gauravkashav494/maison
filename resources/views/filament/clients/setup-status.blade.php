@php
    $status = \App\Clients\ClientSetup::status($getRecord());
    $running = ($status['state'] ?? null) === \App\Clients\ClientSetup::RUNNING;
@endphp

@if ($status)
    {{-- While the database is being built this refreshes itself; it disappears once ready. --}}
    <div @if ($running) wire:poll.3s @endif>
        @if ($running)
            <x-filament::callout icon="heroicon-o-arrow-path" color="info"
                heading="Setting up the store…"
                description="The database is being created and filled with the store's pages, menus and settings. This usually takes under a minute; this page updates on its own." />
        @else
            <x-filament::callout icon="heroicon-o-exclamation-triangle" color="danger"
                heading="The database could not be set up"
                :description="str($status['message'] ?? 'Unknown error')->limit(300) . ' Use “Repair database” above to try again.'" />
        @endif
    </div>
@endif

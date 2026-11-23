<x-filament-panels::page>
    @php($types = $this::types())

    @if (count($types) > 1)
        <x-filament::tabs label="Content type">
            @foreach ($types as $key => $label)
                <x-filament::tabs.item
                    :active="$this->type === $key"
                    wire:click="$set('type', '{{ $key }}')"
                >
                    {{ $label }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    @endif

    {{ $this->table }}
</x-filament-panels::page>

{{--
    Active filter chips, drawn in the table toolbar next to the search field instead of in
    their own row under it. Same badges, remove buttons and "remove all" as Filament's default.
--}}
<div class="fi-ta-toolbar-filter-indicators" role="list" aria-label="{{ __('filament-tables::table.filters.indicator') }}">
    @foreach ($indicators as $indicator)
        <x-filament::badge :color="$indicator->getColor()" role="listitem">
            {{ $indicator->getLabel() }}

            @if ($indicator->isRemovable())
                <x-slot
                    name="deleteButton"
                    :label="__('filament-tables::table.filters.actions.remove.label')"
                    :wire:click="$indicator->getRemoveLivewireClickHandler()"
                    wire:loading.attr="disabled"
                    wire:target="removeTableFilter"
                ></x-slot>
            @endif
        </x-filament::badge>
    @endforeach

    @if ($removeAll)
        {{ $removeAll }}
    @endif
</div>

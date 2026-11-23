{{--
    Site settings tab bar, right-hand end: "View on storefront" for the open tab (`tab` is the
    tab bar's own Alpine state). Tabs with nothing to show (Reviews) have no button.
--}}
@if($links)
    <div class="ms-auto flex shrink-0 items-center ps-3">
        @foreach($links as $tabKey => $link)
            <x-filament::button size="sm" color="primary" icon="heroicon-o-arrow-top-right-on-square" tag="a"
                :href="$link['url']" target="_blank" :tooltip="$link['text']"
                x-cloak x-show="tab === {{ \Illuminate\Support\Js::from($tabKey) }}">
                {{ $link['label'] }}
            </x-filament::button>
        @endforeach
    </div>
@endif

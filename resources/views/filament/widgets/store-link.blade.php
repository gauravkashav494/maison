<x-filament-widgets::widget>
    <div class="fi-store-link">
        <div class="fi-store-link-body">
            <p class="fi-store-link-label">{{ $store->name }} is live at</p>
            <a href="{{ $url }}" target="_blank" rel="noopener" class="fi-store-link-url">{{ $url }}</a>
            <p class="fi-store-link-note">
                @if(! $store->is_active) This store is inactive — the address is not answering until it is activated.
                @elseif($onMainDomain) Also served on the main domain {{ $mainUrl }}.
                @else Share this address with your customers; the main domain shows another store by default. @endif
                @if($url !== $store->entryUrl()) <br>Entry link (works without DNS): <a href="{{ $store->entryUrl() }}" target="_blank" rel="noopener" style="text-decoration:underline">{{ $store->entryUrl() }}</a> @endif
            </p>
        </div>
        <div class="fi-store-link-actions">
            <button type="button" class="fi-btn fi-btn-color-gray fi-btn-size-sm" x-data x-on:click="navigator.clipboard.writeText(@js($url)); $tooltip('Copied')" style="border:1px solid rgb(0 0 0 / .1);padding:.4rem .8rem;border-radius:.5rem;font-size:.8125rem;font-weight:600">Copy link</button>
            <a href="{{ $url }}" target="_blank" rel="noopener" class="fi-btn fi-btn-size-sm" style="background:#1d4ed8;color:#fff;padding:.4rem .8rem;border-radius:.5rem;font-size:.8125rem;font-weight:600">Open store</a>
        </div>
    </div>
</x-filament-widgets::widget>
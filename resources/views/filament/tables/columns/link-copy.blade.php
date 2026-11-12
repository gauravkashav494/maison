@php
    $url = $getState();
    // Optional fixed label (e.g. "Open login"); otherwise show the URL without its scheme.
    $text = $label ?? preg_replace('#^https?://#', '', (string) $url);
@endphp

@if (filled($url))
    <div
        @class(['fi-link-copy flex items-center gap-1.5', 'px-3 py-4' => ! ($inline ?? false)])
        x-data="{
            copied: false,
            copy() {
                const url = @js($url);
                const done = () => { this.copied = true; setTimeout(() => this.copied = false, 1500) };
                if (window.isSecureContext && navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(done);
                } else {
                    const t = document.createElement('textarea');
                    t.value = url; t.style.position = 'fixed'; t.style.opacity = '0';
                    document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove();
                    done();
                }
            },
        }"
    >
        <a
            href="{{ $url }}"
            target="_blank"
            rel="noopener"
            title="{{ $url }}"
            x-on:click.stop
            class="inline-flex min-w-0 items-center gap-1.5 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
        >
            <x-filament::icon :icon="$icon ?? 'heroicon-m-arrow-top-right-on-square'" class="h-4 w-4 shrink-0" />
            <span class="truncate">{{ $text }}</span>
        </a>

        <button
            type="button"
            x-on:click.stop.prevent="copy()"
            x-tooltip="{ content: copied ? 'Copied' : 'Copy URL', theme: $store.theme }"
            :aria-label="copied ? 'Copied' : 'Copy URL'"
            class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
        >
            <x-filament::icon icon="heroicon-m-clipboard-document" class="h-4 w-4" x-show="! copied" />
            <x-filament::icon icon="heroicon-m-check" class="h-4 w-4 text-success-600" x-show="copied" x-cloak />
        </button>
    </div>
@elseif (filled($placeholder ?? null))
    <div @class(['fi-link-copy text-sm text-gray-400 dark:text-gray-500', 'px-3 py-4' => ! ($inline ?? false)])>{{ $placeholder }}</div>
@endif

{{-- The agent setup code, shown once after it is made (ViewClient::showSetupCodeAction). --}}
<div
    class="space-y-4 text-sm text-gray-700 dark:text-gray-300"
    x-data="{
        copied: false,
        copy() {
            const code = @js($code);
            const done = () => { this.copied = true; setTimeout(() => this.copied = false, 1500) };
            if (window.isSecureContext && navigator.clipboard) {
                navigator.clipboard.writeText(code).then(done);
            } else {
                const t = document.createElement('textarea');
                t.value = code; t.style.position = 'fixed'; t.style.opacity = '0';
                document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove();
                done();
            }
        },
    }"
>
    <div class="flex items-start gap-2">
        <code class="block min-w-0 flex-1 break-all rounded-lg bg-gray-100 px-3 py-2 font-mono text-xs text-gray-900 dark:bg-white/5 dark:text-white">{{ $code }}</code>
        <x-filament::button size="sm" color="gray" icon="heroicon-m-clipboard-document" x-on:click="copy()">
            <span x-show="! copied">Copy</span>
            <span x-show="copied" x-cloak>Copied</span>
        </x-filament::button>
    </div>

    <p class="font-medium text-danger-600 dark:text-danger-400">
        It works like a password and is not shown again. Send it privately.
    </p>

    <ol class="list-decimal space-y-1 ps-5">
        <li>Make sure <span class="font-medium">Billing software connection</span> on this page has the billing software's API address and key.</li>
        <li>Download the installer from <span class="font-medium">Download the agent</span> on this page and run it on the shop PC.</li>
        <li>Paste this code when it asks. That is all it asks: it checks it can reach the store and the billing software before it installs.</li>
    </ol>
</div>

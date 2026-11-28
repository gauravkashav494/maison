{{--
    The agent's installer for each Windows version, with the one this computer needs highlighted
    (SyncStatus). Windows 10 and 11 take the main installers; Windows 7 and 8.1 their own (built
    with Go 1.20, the last Go to run there). The browser can only describe the computer it runs on,
    so the note says so: the agent may be installed on another PC. Chrome and Edge report Windows'
    bitness and version (7, 8 and 8.1 report version 0.x); elsewhere the user agent is a hint at
    best ("Windows NT 6.x" is 7 to 8.1), and with no answer nothing is highlighted.

    Filament's own buttons, so they look like every other button in the panel: each build has a
    primary one, shown when it is the recommended build, and a grey one otherwise.
--}}
<div
    class="space-y-4 text-sm"
    x-data="{
        arch: null,
        async detect() {
            const ua = navigator.userAgent;
            let old = /Windows NT 6\./.test(ua);
            const hints = navigator.userAgentData;
            if (hints && hints.getHighEntropyValues) {
                try {
                    const v = await hints.getHighEntropyValues(['platform', 'platformVersion', 'bitness', 'architecture']);
                    if (v.platform && v.platform !== 'Windows') { this.arch = 'other'; return; }
                    if (v.platformVersion) { old = Number(v.platformVersion.split('.')[0]) === 0; }
                    const suffix = old ? '-win7' : '';
                    // Windows on ARM runs the 32-bit build on every version.
                    if (v.architecture === 'arm') { this.arch = '386' + suffix; return; }
                    if (v.bitness === '64') { this.arch = 'amd64' + suffix; return; }
                    if (v.bitness === '32') { this.arch = '386' + suffix; return; }
                } catch (e) {}
            }
            if (! /Windows/.test(ua)) { this.arch = 'other'; return; }
            this.arch = (/Win64|WOW64|x64/.test(ua) ? 'amd64' : '386') + (old ? '-win7' : '');
        },
    }"
    x-init="detect()"
>
    @foreach ([
        'Windows 10 or 11' => ['amd64' => '64-bit Windows 10 / 11', '386' => '32-bit Windows 10 / 11'],
        'Windows 7 or 8.1' => ['amd64-win7' => '64-bit Windows 7 / 8.1', '386-win7' => '32-bit Windows 7 / 8.1'],
    ] as $group => $builds)
        <div class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $group }}</p>
            <div class="flex flex-wrap gap-3">
                @foreach ($builds as $arch => $label)
                    @php($action = 'downloadAgent'.str_replace('-', '', $arch))
                    <x-filament::button
                        icon="heroicon-m-arrow-down-tray"
                        wire:click="mountAction('{{ $action }}')"
                        x-show="arch === {{ \Illuminate\Support\Js::from($arch) }}"
                        x-cloak
                    >
                        Installer for {{ $label }}
                        <x-filament::badge size="sm" color="gray" class="ms-1">Recommended</x-filament::badge>
                    </x-filament::button>
                    <x-filament::button
                        color="gray"
                        outlined
                        icon="heroicon-m-arrow-down-tray"
                        wire:click="mountAction('{{ $action }}')"
                        x-show="arch !== {{ \Illuminate\Support\Js::from($arch) }}"
                    >
                        Installer for {{ $label }}
                    </x-filament::button>
                @endforeach
            </div>
        </div>
    @endforeach

    <p class="text-gray-500 dark:text-gray-400">
        <span x-show="arch === 'amd64'" x-cloak>This computer runs 64-bit Windows 10 or 11.</span>
        <span x-show="arch === '386'" x-cloak>This computer needs the 32-bit installer.</span>
        <span x-show="arch && arch.endsWith('-win7')" x-cloak>This computer runs Windows 7 or 8.1.</span>
        <span x-show="arch === 'other'" x-cloak>This computer isn't running Windows.</span>
        If you will install the agent on a different PC, choose for that PC: on it, open
        <span class="font-medium">Settings → System → About</span> (Windows 7: <span class="font-medium">Control Panel → System</span>)
        and look at the Windows version and <span class="font-medium">System type</span>. Not sure of the bitness? The 32-bit installer works on both.
    </p>
</div>

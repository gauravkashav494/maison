<x-filament-panels::page>
    @php
        $active = $this->getActiveId();
        $activatedAt = $this->getActivatedAt();
    @endphp

    <style>
        .tpl-intro { border-radius: .75rem; border: 1px solid rgb(0 0 0 / .08); background: #fff; padding: 1rem 1.25rem; font-size: .875rem; color: #4b5563; line-height: 1.5; }
        .tpl-grid { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
        .tpl-card { display: flex; flex-direction: column; overflow: hidden; border-radius: .75rem; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / .05); outline: 1px solid rgb(0 0 0 / .08); }
        .tpl-card.is-active { outline: 2px solid rgb(22 163 74); }
        .tpl-media { position: relative; aspect-ratio: 16 / 10; background: #f3f4f6; }
        .tpl-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: top; }
        .tpl-media .tpl-empty { position: absolute; inset: 0; display: grid; place-items: center; color: #9ca3af; font-size: .875rem; }
        .tpl-badges { position: absolute; left: .75rem; top: .75rem; display: flex; gap: .5rem; }
        .tpl-body { display: flex; flex: 1; flex-direction: column; gap: .75rem; padding: 1.25rem; }
        .tpl-body h3 { margin: 0; font-size: 1rem; font-weight: 600; color: #111827; }
        .tpl-body p { margin: .25rem 0 0; font-size: .875rem; line-height: 1.55; color: #6b7280; }
        .tpl-meta { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; font-size: .75rem; color: #6b7280; }
        .tpl-meta dt { font-weight: 500; color: #374151; }
        .tpl-meta dd { margin: 0; }
        .tpl-meta code { font-family: ui-monospace, monospace; }
        .tpl-actions { margin-top: auto; display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding-top: .5rem; }
        .tpl-actions .tpl-links { margin-left: auto; display: flex; flex-wrap: wrap; gap: .75rem; }
        .tpl-help { margin: 0; padding-left: 1.25rem; font-size: .875rem; color: #4b5563; line-height: 1.6; }
        .tpl-help li + li { margin-top: .25rem; }
        .dark .tpl-intro, .dark .tpl-card { background: rgb(255 255 255 / .05); outline-color: rgb(255 255 255 / .1); color: #d1d5db; }
        .dark .tpl-body h3 { color: #fff; } .dark .tpl-body p, .dark .tpl-meta, .dark .tpl-help { color: #9ca3af; } .dark .tpl-meta dt { color: #e5e7eb; }
    </style>

    <div class="tpl-intro">
        Templates change only how the storefront looks. Products, categories, pages, navigation content, orders, customers, SEO and checkout settings are shared and never lost when you switch. Every URL stays the same.
    </div>

    <div class="tpl-grid">
        @foreach($this->getTemplates() as $template)
            @php
                $isActive = $template->id() === $active;
                $installed = $template->isInstalled();
            @endphp
            <div class="tpl-card {{ $isActive ? 'is-active' : '' }}">
                <div class="tpl-media">
                    @if($template->thumbnail() && file_exists(public_path($template->thumbnail())))
                        <img src="{{ $template->thumbnail() }}?v={{ filemtime(public_path($template->thumbnail())) }}" alt="{{ $template->name() }} preview">
                    @else
                        <div class="tpl-empty">No preview image yet</div>
                    @endif
                    <div class="tpl-badges">
                        @if($isActive)
                            <x-filament::badge color="success" icon="heroicon-m-check-circle">Active</x-filament::badge>
                        @endif
                        @unless($installed)
                            <x-filament::badge color="warning">Not installed</x-filament::badge>
                        @endunless
                    </div>
                </div>

                <div class="tpl-body">
                    <div>
                        <h3>{{ $template->name() }}</h3>
                        <p>{{ $template->description() }}</p>
                    </div>

                    <dl class="tpl-meta">
                        <div><dt>Identifier</dt><dd><code>{{ $template->id() }}</code></dd></div>
                        <div><dt>Menus</dt><dd>{{ count($template->menuLocations()) }} locations</dd></div>
                        @if($isActive && $activatedAt)
                            <div style="grid-column: span 2"><dt>Activated</dt><dd>{{ \Illuminate\Support\Carbon::parse($activatedAt)->diffForHumans() }}</dd></div>
                        @endif
                    </dl>

                    <div class="tpl-actions">
                        @if($isActive)
                            <x-filament::button color="success" icon="heroicon-m-check-circle" disabled>Currently active</x-filament::button>
                        @elseif($installed)
                            {{ ($this->activateAction)(['template' => $template->id()]) }}
                        @else
                            {{ ($this->installAction)(['template' => $template->id()]) }}
                        @endif
                        @if($installed)
                            {{ ($this->previewAction)(['template' => $template->id()]) }}
                        @endif
                        <div class="tpl-links">
                            @foreach($template->adminPages() as $page)
                                @if(class_exists($page))
                                    <x-filament::link :href="$page::getUrl()" size="sm" icon="heroicon-m-adjustments-horizontal">{{ $page::getNavigationLabel() }}</x-filament::link>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-filament::section heading="How switching works" collapsible collapsed>
        <ul class="tpl-help">
            <li><strong>Preview</strong> shows a template to you only (a bar at the bottom of the storefront reminds you). Visitors keep seeing the active template.</li>
            <li><strong>Activate</strong> makes the template live for everyone after a confirmation. Caches are cleared automatically.</li>
            <li>Each template has its own homepage content, navigation menus and header/footer settings under <em>Appearance</em>. Catalogue, pages, journal, orders, customers, promo codes, checkout and SEO defaults are shared.</li>
            <li>Products, categories and collections have a <em>Visible in</em> option (all templates or one template) so a grocery catalogue and a fashion catalogue can live side by side.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>

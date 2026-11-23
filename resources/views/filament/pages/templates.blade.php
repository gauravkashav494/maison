<x-filament-panels::page>
    <style>
        .tpl-intro { border-radius: .75rem; border: 1px solid rgb(0 0 0 / .08); background: #fff; padding: 1rem 1.25rem; font-size: .875rem; color: #4b5563; line-height: 1.5; }
        .tpl-grid { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
        .tpl-card { display: flex; flex-direction: column; overflow: hidden; border-radius: .75rem; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / .05); outline: 1px solid rgb(0 0 0 / .08); }
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
        Every store is built on one of these templates, chosen on the store itself, and visitors see that template on the store's own address. Here you can install a template's demo content, build its public theme preview and open its settings.
    </div>

    <div class="tpl-grid">
        @foreach($this->getTemplates() as $template)
            @php $installed = $template->isInstalled(); @endphp
            <div class="tpl-card">
                <div class="tpl-media">
                    @if($template->thumbnail() && file_exists(public_path($template->thumbnail())))
                        <img src="{{ $template->thumbnail() }}?v={{ filemtime(public_path($template->thumbnail())) }}" alt="{{ $template->name() }} preview">
                    @else
                        <div class="tpl-empty">No preview image yet</div>
                    @endif
                    <div class="tpl-badges">
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
                    </dl>

                    <div class="tpl-actions">
                        @unless($installed)
                            {{ ($this->installAction)(['template' => $template->id()]) }}
                        @endunless
                        {{ ($this->previewAction)(['template' => $template->id()]) }}
                        {{ ($this->themeAction)(['template' => $template->id()]) }}
                        <div class="tpl-links">
                            @foreach($template->adminPages() as $page)
                                {{-- Template settings belong to each client; only pages on this panel can be linked. --}}
                                @if(in_array($page, filament()->getCurrentOrDefaultPanel()->getPages(), true))
                                    <x-filament::link :href="$page::getUrl()" size="sm" icon="heroicon-m-adjustments-horizontal">{{ $page::getNavigationLabel() }}</x-filament::link>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-filament::section heading="How templates work" collapsible collapsed>
        <ul class="tpl-help">
            <li>A store's template is set on the store (Platform → Stores). Its subdomain or custom domain always shows that template; the main domain is this admin panel.</li>
            <li><strong>Preview</strong> opens the template's public theme preview, a sample store visitors can browse but not order from. <strong>Set up / Update theme</strong> builds it.</li>
            <li>Each template has its own homepage content, navigation menus and header/footer settings under <em>Appearance</em>. Catalogue, pages, journal, orders, customers, promo codes, checkout and SEO defaults are shared.</li>
            <li>Products, categories and collections have a <em>Visible in</em> option (all templates or one template) so a grocery catalogue and a fashion catalogue can live side by side.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>

@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $siteName = setting('site.name', config('app.name'));
    $image = $this->visualImage();
    $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l4 4L19 6"/></svg>';
    $mark = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 6.5L12 21 5 9.5z"/><path d="M5 9.5h14M12 3l-3.5 6.5L12 21l3.5-11.5z"/></svg>';
@endphp
    <div class="fi-login">
        {{-- Visual side --}}
        <aside class="fi-login-visual" @if($image) style="background-image: url('{{ $image }}')" @endif>
            <div class="fi-login-visual-shade"></div>
            <div class="fi-login-visual-body">
                <a href="{{ url('/') }}" class="fi-login-brand" target="_blank" rel="noopener">
                    <span class="fi-login-brand-mark">{!! $mark !!}</span>
                    <span>{{ $siteName }}</span>
                </a>
                <div class="fi-login-visual-copy">
                    <p class="fi-login-eyebrow">Store administration</p>
                    <h2 class="fi-login-visual-title">Everything behind the storefront, in one place.</h2>
                    <p class="fi-login-visual-text">Orders, catalogue, content and templates for {{ $siteName }}.</p>
                    <ul class="fi-login-points">
                        <li><span class="fi-login-point-ico">{!! $check !!}</span> Orders &amp; fulfilment</li>
                        <li><span class="fi-login-point-ico">{!! $check !!}</span> Products, categories &amp; collections</li>
                        <li><span class="fi-login-point-ico">{!! $check !!}</span> Homepage, pages &amp; storefront templates</li>
                    </ul>
                </div>
                <p class="fi-login-visual-foot">© {{ date('Y') }} {{ $siteName }}</p>
            </div>
        </aside>

        {{-- Form side --}}
        <main id="fi-main-content" class="fi-login-panel">
            <div class="fi-login-card fi-simple-page">
                {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}
                <div class="fi-login-head">
                    <span class="fi-login-head-mark">{!! $mark !!}</span>
                    <h1 class="fi-login-title">Welcome back</h1>
                    <p class="fi-login-subtitle">Sign in to the {{ $siteName }} admin.</p>
                </div>
                <div class="fi-simple-page-content">
                    {{ $this->content }}
                </div>
                <x-filament-actions::modals />
                {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
                <p class="fi-login-help">Trouble signing in? Contact the site owner to reset your access.</p>
            </div>
        </main>
    </div>

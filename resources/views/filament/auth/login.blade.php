@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $page = $this->page();
    $siteName = $page['brand'] ?? filament()->getBrandName();
    $image = $this->visualImage();
    $mark = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 6.5L12 21 5 9.5z"/><path d="M5 9.5h14M12 3l-3.5 6.5L12 21l3.5-11.5z"/></svg>';
@endphp
<div class="fi-login" data-style="{{ $this->style() }}" @if($image) style="--login-image: url('{{ $image }}')" @endif>
    <div class="fi-login-glow fi-login-glow-a"></div>
    <div class="fi-login-glow fi-login-glow-b"></div>

    <div class="fi-login-card">
        {{-- Visual panel --}}
        <aside class="fi-login-visual" @if($image) style="background-image: url('{{ $image }}')" @endif>
            <div class="fi-login-visual-shade"></div>
            <div class="fi-login-visual-body">
                <a href="{{ url('/') }}" class="fi-login-brand" target="_blank" rel="noopener">
                    <span class="fi-login-mark">{!! $mark !!}</span>
                    <span class="fi-login-brand-name">{{ $siteName }}</span>
                </a>
                <div class="fi-login-quote">
                    <span class="fi-login-rule"></span>
                    <p class="fi-login-quote-text">{{ $page['quote'] }}</p>
                    <p class="fi-login-quote-sub">{{ $page['quote_sub'] }}</p>
                </div>
            </div>
        </aside>

        {{-- Form panel --}}
        <main id="fi-main-content" class="fi-login-panel fi-simple-page">
            {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}
            <div class="fi-login-form">
                <span class="fi-login-mark fi-login-mark-dark">{!! $mark !!}</span>
                <p class="fi-login-kicker">{{ $page['kicker'] }}</p>
                <h1 class="fi-login-title">{{ $page['heading'] }}</h1>
                <p class="fi-login-subtitle">{{ $page['subtitle'] }}</p>
                <div class="fi-simple-page-content">
                    {{ $this->content }}
                </div>
                @if (filled($page['help'] ?? null))<p class="fi-login-help">{{ $page['help'] }}</p>@endif
            </div>
            <x-filament-actions::modals />
            {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
        </main>
    </div>

    <p class="fi-login-foot">© {{ date('Y') }} {{ $siteName }} · <a href="{{ url('/') }}" target="_blank" rel="noopener">Visit the storefront</a></p>
</div>

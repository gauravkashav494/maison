@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $page = $this->page();
    $siteName = $page['brand'] ?? filament()->getBrandName();
    $image = $this->visualImage();
    $mark = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 6.5L12 21 5 9.5z"/><path d="M5 9.5h14M12 3l-3.5 6.5L12 21l3.5-11.5z"/></svg>';
@endphp
<div class="fi-login" data-style="{{ $this->style() }}" @if($image) style="--login-image: url('{{ $image }}')" @endif>
    <div class="fi-login-glows" aria-hidden="true">
        <div class="fi-login-glow fi-login-glow-a"></div>
        <div class="fi-login-glow fi-login-glow-b"></div>
    </div>

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
                @if ($this->retryUntil)
                    <div
                        wire:key="login-throttle-{{ $this->retryUntil }}"
                        x-data="{
                            endsAt: {{ $this->retryUntil }},
                            remaining: 0,
                            timer: null,
                            init() { this.update(); this.timer = window.setInterval(() => this.update(), 1000) },
                            update() {
                                this.remaining = Math.max(0, this.endsAt - Math.floor(Date.now() / 1000))
                                if (this.remaining === 0 && this.timer) window.clearInterval(this.timer)
                            },
                            destroy() { if (this.timer) window.clearInterval(this.timer) },
                        }"
                        x-cloak
                        x-show="remaining > 0"
                        class="fi-login-throttle"
                        role="status"
                        aria-live="polite"
                    >
                        <span class="fi-login-throttle-clock" aria-hidden="true">
                            <strong x-text="remaining">0</strong>
                            <span x-text="remaining === 1 ? 'SEC' : 'SECS'">SECS</span>
                        </span>
                        <span class="fi-login-throttle-copy">
                            <strong class="fi-login-throttle-title">Sign-in temporarily paused</strong>
                            <span class="fi-login-throttle-message">Too many login attempts. Please wait for the countdown to finish.</span>
                        </span>
                    </div>
                @endif
                <div class="fi-simple-page-content">
                    {{ $this->content }}
                </div>
                @if (filled($page['help'] ?? null))<p class="fi-login-help">{{ $page['help'] }}</p>@endif
            </div>
            <x-filament-actions::modals />
            {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
        </main>
    </div>

    @if ($this->settingsPreview)
        {{-- Site settings → Sign-in page → "View sign-in page": a signed-in team member's preview. --}}
        <div role="status" style="position: fixed; left: 50%; bottom: 16px; transform: translateX(-50%); z-index: 9999; max-width: min(92vw, 640px); text-align: center; background: #0f172a; color: #fff; font: 600 13px/1.4 system-ui, sans-serif; padding: 9px 14px; border-radius: 18px; box-shadow: 0 8px 24px rgb(0 0 0 / .25);">
            Preview of your team's sign-in page, as set in Site settings → Sign-in page. You are signed in, so this form does nothing here.
        </div>
    @endif

    <p class="fi-login-foot">© {{ date('Y') }} {{ $siteName }}@if(filament()->getCurrentPanel()?->getId() !== 'admin') · <a href="{{ url('/') }}" target="_blank" rel="noopener">Visit the storefront</a>@endif</p>
</div>

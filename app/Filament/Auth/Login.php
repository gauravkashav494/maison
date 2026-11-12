<?php

namespace App\Filament\Auth;

use App\Clients\ClientContext;
use App\Models\Client;
use App\Models\Setting;
use App\Support\Media;
use App\Templates\Template;
use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Split-screen sign-in: brand visual on the left, Filament's standard login form on the right.
 *
 * Super admin (main domain): the platform's look, from Platform settings.
 * A store's team (/client/login): that store's own page — its template's image and words
 * (Template::teamLogin), with anything the client changed in Site settings → Sign-in page.
 */
class Login extends BaseLogin
{
    protected string $view = 'filament.auth.login';

    /** Full-bleed layout (no centred card) so the page can split into visual + form. */
    protected static string $layout = 'filament-panels::components.layout.base';

    public const STYLES = [
        'classic' => 'Classic — floating split card on navy',
        'editorial' => 'Editorial — full-height image, cream page, serif',
        'midnight' => 'Midnight — full-screen image with a frosted glass card',
        'minimal' => 'Minimal — plain white, centred form, no image',
    ];

    /** Default super-admin sign-in image: a calm workspace — the place a business is run from. */
    public const PLATFORM_IMAGE = 'https://images.unsplash.com/photo-1752224543110-35faed040b91?w=1600&h=1600&q=80&auto=format&fit=crop';

    /** @var array<string, mixed>|null */
    private ?array $page = null;

    /** Everything the view shows: style, image and words. */
    public function page(): array
    {
        return $this->page ??= $this->isPlatform() ? $this->platformPage() : $this->storePage();
    }

    public function visualImage(): ?string
    {
        return Media::url($this->page()['image'] ?? null) ?: null;
    }

    public function style(): string
    {
        $style = (string) ($this->page()['style'] ?? 'classic');

        return array_key_exists($style, self::STYLES) ? $style : 'classic';
    }

    private function platformPage(): array
    {
        return [
            // The old platform-wide style is kept until Platform settings is saved.
            'style' => setting('platform.admin_login_style', setting('site.admin_login_style', 'classic')),
            'image' => setting('platform.login_image', self::PLATFORM_IMAGE),
            'brand' => filament()->getBrandName(),
            'kicker' => 'Administration',
            'heading' => 'Welcome back',
            'subtitle' => 'Sign in to manage '.filament()->getBrandName().'.',
            'quote' => 'Where craft meets commerce.',
            'quote_sub' => 'Clients, stores, plans and templates — all in one calm place.',
            'help' => 'Trouble signing in? Ask another super admin to reset your access.',
        ];
    }

    private function storePage(): array
    {
        $client = $this->storeClient();
        $defaults = $client?->templateObject()?->teamLogin() ?? Template::defaultTeamLogin();

        // Blank fields in the client's own copy mean "use the store's default".
        $own = array_filter($client ? (Setting::forClient($client->id, 'login') ?? []) : [], fn ($value) => filled($value));
        $page = array_replace($defaults, $own);

        $name = (string) ($client?->name ?? filament()->getBrandName());

        return ['brand' => $name] + array_map(fn ($value) => is_string($value) ? str_replace(':name', $name, $value) : $value, $page);
    }

    /**
     * The store this sign-in page belongs to. Nobody is signed in yet, so it comes from the
     * subdomain — for showing the right page only; who may sign in is still decided by the
     * account itself (ClientUser::canAccessPanel).
     */
    private function storeClient(): ?Client
    {
        $client = app(ClientContext::class)->client();
        if ($client) {
            return $client;
        }
        $id = Client::idForHost(request()->getHost());

        return $id ? Client::find($id) : null;
    }

    private function isPlatform(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'admin';
    }
}

<?php

namespace App\Filament\Auth;

use App\Support\Media;
use Filament\Auth\Pages\Login as BaseLogin;

/** Split-screen sign-in: brand visual on the left, Filament's standard login form on the right. */
class Login extends BaseLogin
{
    protected string $view = 'filament.auth.login';

    /** Full-bleed layout (no centred card) so the page can split into visual + form. */
    protected static string $layout = 'filament-panels::components.layout.base';

    /** Image shown beside the form — the site's page header image (Site settings → Brand), if one is set. */
    public function visualImage(): ?string
    {
        return Media::url(setting('site.page_header_image')) ?: null;
    }
}

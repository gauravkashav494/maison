<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Installable web app support shared by every template: the manifest is built from the
 * active template's colours and icons so "Add to home screen" matches the storefront.
 */
class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $template = template();
        $pwa = $template->pwa();
        $name = $pwa['name'] ?? setting('site.name', config('app.name'));
        $base = '/templates/'.$template->id();

        return response()->json([
            'name' => $name,
            'short_name' => mb_strlen($name) <= 12 ? $name : \Illuminate\Support\Str::before($name, ' '),
            'description' => $template->description(),
            'id' => '/',
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => $pwa['theme_color'],
            'background_color' => $pwa['background_color'],
            'icons' => [
                ['src' => "$base/icon-192.png", 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => "$base/icon-512.png", 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => "$base/icon-maskable-512.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Shop', 'url' => route('shop.index', [], false), 'icons' => [['src' => "$base/icon-192.png", 'sizes' => '192x192']]],
                ['name' => 'Cart', 'url' => route('cart', [], false), 'icons' => [['src' => "$base/icon-192.png", 'sizes' => '192x192']]],
                ['name' => 'My account', 'url' => route('account.index', [], false), 'icons' => [['src' => "$base/icon-192.png", 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function offline(): View
    {
        return view('pwa.offline', ['pwa' => template()->pwa(), 'templateId' => template()->id()]);
    }
}

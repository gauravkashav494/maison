<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('account.index'));

        // The entry-link cookies hold a public slug and nothing else: they only say which
        // client's (or store's) storefront to show, and they grant nothing on their own —
        // every request still resolves the client server-side and scopes the data to it.
        // Leaving them unencrypted keeps a shared link working across app keys and makes the
        // choice visible to the visitor, which is what it is.
        $middleware->encryptCookies(except: [
            \App\Models\Client::COOKIE,
            \App\Models\Storefront::COOKIE,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\ResolveTemplate::class,
            \App\Http\Middleware\DiscourageSearchEngines::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

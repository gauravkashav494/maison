<?php

use App\Http\Middleware\DiscourageSearchEngines;
use App\Http\Middleware\ResolveTemplate;
use App\Models\Client;
use App\Models\Storefront;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
            Client::COOKIE,
            Storefront::COOKIE,
        ]);
        // Signing out works without a valid form token: the idle sign-out (partials/session-timeout)
        // is sent from a page whose session may already have expired, and refusing it would leave
        // the "remember me" cookie in place, signing the visitor straight back in. A forged
        // sign-out can only sign someone out. Storefront, client panel and Super Admin panel.
        $middleware->validateCsrfTokens(except: ['logout', 'client/logout']);
        $middleware->web(append: [
            ResolveTemplate::class,
            DiscourageSearchEngines::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The session ended (2 hours without use) before a form was sent. Instead of Laravel's
        // "Page expired" page, go back to the form with what was typed (a signed-out visitor is
        // then sent on to sign in). Livewire and fetch requests keep the 419 answer; the page
        // reloads on it (partials/session-timeout).
        // Laravel turns the TokenMismatchException into a 419 HttpException before this runs.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }
            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                return response()->json(['message' => 'Your session has expired. Reload the page and try again.', 'session_expired' => true], 419);
            }

            $back = url()->previous();
            if ($back === $request->fullUrl() || str_ends_with(parse_url($back, PHP_URL_PATH) ?? '', '/logout')) {
                $back = url('/');
            }

            // Signing out with an old page: the session is gone already, so that is done.
            if (str_ends_with($request->path(), 'logout')) {
                return redirect()->to($back);
            }

            return redirect()->to($back)
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password']));
        });
    })->create();

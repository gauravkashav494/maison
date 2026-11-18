<?php

namespace App\Support\Activity;

use App\Models\ClientUser;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;

/**
 * Sign-ins, sign-outs, failed sign-ins and password resets of a client's staff, in the
 * client's activity log. A failed attempt is logged only when the email belongs to one of the
 * client's accounts — an unknown email has no client to report to.
 */
class SignInActivity
{
    public static function register(): void
    {
        Event::listen(Login::class, function (Login $event) {
            if ($event->guard === 'client' && $event->user instanceof ClientUser) {
                self::log('signed in', 'signed in', $event->user);
            }
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->guard === 'client' && $event->user instanceof ClientUser) {
                self::log('signed out', 'signed out', $event->user);
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            if ($event->guard === 'client' && $event->user instanceof ClientUser) {
                self::log('sign-in failed', 'failed sign-in attempt (wrong password)', $event->user);
            }
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event) {
            if ($event->user instanceof ClientUser) {
                self::log('password reset', 'reset their password from the "Forgot password" email', $event->user);
            }
        });
    }

    private static function log(string $action, string $description, ClientUser $user): void
    {
        app(ActivityLog::class)->record($action, $description, area: 'Sign-in', actor: $user, subjectName: (string) $user->name, subjectType: 'Account');
    }
}

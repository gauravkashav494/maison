<?php

namespace App\Clients;

use App\Models\ClientUser;
use App\Notifications\ClientAdminInvitation;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Password;

/**
 * Invites a client's staff member by email: a one-time "set your password" link on the client's
 * own address (their subdomain or custom domain), then their sign-in page. Nobody else ever
 * knows the password.
 */
class ClientInvitation
{
    public function send(ClientUser $user): void
    {
        $client = $user->client;
        $root = rtrim($client->publicUrl(), '/');

        $token = Password::broker('client_users')->createToken($user);

        // The link is signed, so it has to be built for the client's host, not the one the
        // super admin happens to be on.
        $url = app('url');
        $url->forceRootUrl($root);
        try {
            $setPasswordUrl = Filament::getPanel('client')->getResetPasswordUrl($token, $user);
        } finally {
            $url->forceRootUrl(null);
        }

        $user->notify(new ClientAdminInvitation(
            setPasswordUrl: $setPasswordUrl,
            loginUrl: $client->loginUrl(),
            validForHours: (int) ceil(config('auth.passwords.client_users.expire', 60) / 60),
        ));
    }
}

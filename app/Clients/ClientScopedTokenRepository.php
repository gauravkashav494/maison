<?php

namespace App\Clients;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

/**
 * Password-reset tokens for accounts that exist once per client (shoppers).
 *
 * The same email may be a shopper of several stores. Laravel's stock repository keys a
 * token on the email alone, so a link sent by store B would also reset the account on store A,
 * and asking store A for a link would cancel store B's. Here every read and write is keyed on
 * the email together with the account's own client_id.
 */
class ClientScopedTokenRepository extends DatabaseTokenRepository
{
    public function create(CanResetPasswordContract $user)
    {
        $this->deleteExisting($user);

        $token = $this->createNewToken();

        $this->getTable()->insert($this->getPayload($user->getEmailForPasswordReset(), $token) + [
            'client_id' => $this->clientId($user),
        ]);

        return $token;
    }

    protected function deleteExisting(CanResetPasswordContract $user)
    {
        return $this->forUser($user)->delete();
    }

    public function exists(CanResetPasswordContract $user, #[\SensitiveParameter] $token)
    {
        $record = (array) $this->forUser($user)->first();

        return $record &&
               ! $this->tokenExpired($record['created_at']) &&
                 $this->hasher->check($token, $record['token']);
    }

    public function recentlyCreatedToken(CanResetPasswordContract $user)
    {
        $record = (array) $this->forUser($user)->first();

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    private function forUser(CanResetPasswordContract $user)
    {
        return $this->getTable()
            ->where('client_id', $this->clientId($user))
            ->where('email', $user->getEmailForPasswordReset());
    }

    private function clientId(CanResetPasswordContract $user): int
    {
        // An account outside any client never gets a token that another client could match.
        return (int) ($user->client_id ?? 0);
    }
}

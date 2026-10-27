<?php

namespace App\Contracts;

/**
 * What the authorization layer needs to know about whoever is signed into an admin panel,
 * whether that is a SuperAdmin (App\Models\User) or a client's staff account
 * (App\Models\ClientUser). Policies type against this, never against one model, so a
 * ClientUser can never be mistaken for a SuperAdmin by a missing check.
 */
interface AdminIdentity
{
    public function isSuperAdmin(): bool;

    /** Client this account belongs to; null only for a SuperAdmin. */
    public function clientId(): ?int;

    /** Template id the account is confined to (null = unrestricted SuperAdmin). */
    public function managedTemplateId(): ?string;

    /** False for a disabled account, or one whose client has been disabled. */
    public function isActiveAccount(): bool;
}

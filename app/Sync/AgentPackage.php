<?php

namespace App\Sync;

use RuntimeException;

/**
 * The agent program served to installed agents, and its signatures.
 *
 * An agent runs this code on a client's PC, so it only accepts a build signed with a private key
 * that is kept off the web server. A build that has not been signed is never delivered.
 */
class AgentPackage
{
    public const ARCHITECTURES = ['amd64', '386'];

    private readonly string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? resource_path('agent');
    }

    public function binaryPath(string $arch): string
    {
        return "{$this->dir}/dist/ecomvision-agent-{$arch}.exe";
    }

    public function exists(string $arch): bool
    {
        return is_file($this->binaryPath($arch));
    }

    public function version(string $arch): ?string
    {
        return $this->exists($arch) ? hash_file('sha256', $this->binaryPath($arch)) : null;
    }

    public function signature(string $arch): ?string
    {
        $path = $this->binaryPath($arch).'.sig';

        return is_file($path) ? trim((string) file_get_contents($path)) : null;
    }

    public function publicKey(): ?string
    {
        $path = $this->dir.'/signing.pub';

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function isSigned(string $arch): bool
    {
        $signature = $this->signature($arch);
        $key = $this->publicKey();
        if ($signature === null || $key === null || ! $this->exists($arch)) {
            return false;
        }

        return openssl_verify((string) file_get_contents($this->binaryPath($arch)), (string) base64_decode($signature), $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /** Create a signing key at $privatePath, which must be outside the project. */
    public function generateKey(string $privatePath): void
    {
        if (file_exists($privatePath)) {
            throw new RuntimeException('A key already exists there. Replacing it would cut off every installed agent.');
        }

        $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false || ! openssl_pkey_export($key, $pem)) {
            throw new RuntimeException('OpenSSL could not create a key.');
        }

        file_put_contents($privatePath, $pem);
        chmod($privatePath, 0600);
        file_put_contents($this->dir.'/signing.pub', openssl_pkey_get_details($key)['key']);
    }

    /** @return array<int, string> the architectures signed */
    public function sign(string $privatePath): array
    {
        $key = is_file($privatePath) ? openssl_pkey_get_private((string) file_get_contents($privatePath)) : false;
        if ($key === false) {
            throw new RuntimeException('That file is not a readable private key.');
        }

        $signed = [];
        foreach (self::ARCHITECTURES as $arch) {
            if (! $this->exists($arch)) {
                continue;
            }

            openssl_sign((string) file_get_contents($this->binaryPath($arch)), $signature, $key, OPENSSL_ALGO_SHA256);
            file_put_contents($this->binaryPath($arch).'.sig', base64_encode($signature)."\n");

            if (! $this->isSigned($arch)) {
                throw new RuntimeException('That key does not match resources/agent/signing.pub.');
            }
            $signed[] = $arch;
        }

        return $signed;
    }

    /** What the person installing pastes in. It contains the token, so treat it as a password. */
    public static function setupCode(string $apiUrl, string $token): string
    {
        return 'EV1.'.rtrim(strtr(base64_encode($apiUrl."\n".$token), '+/', '-_'), '=');
    }
}

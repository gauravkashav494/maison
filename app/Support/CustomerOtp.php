<?php

namespace App\Support;

use App\Clients\ClientContext;
use App\Services\WhatsApp;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A one-time code a shopper receives on WhatsApp to prove the phone number is theirs.
 *
 * One code is pending per visitor, kept in their session with what it unlocks: the sign-up
 * details ("register"), the checkout form ("checkout"), the account to sign in to
 * ("login") or the account whose password is being reset ("reset"). Only a keyed hash of the code is kept. A code lasts five minutes, allows a few
 * wrong entries, and only works on the storefront (client) it was sent from. The number of
 * codes one phone can receive is limited, whatever session asks for them.
 */
class CustomerOtp
{
    public const REGISTER = 'register';

    public const CHECKOUT = 'checkout';

    public const LOGIN = 'login';

    /** Forgot password: once the number is proven, the shopper chooses a new password. */
    public const RESET = 'reset';

    private const SESSION_KEY = 'customer_otp';

    public function __construct(private readonly WhatsApp $whatsapp) {}

    /**
     * Whether codes are asked at all (services.otp.enabled, which follows WHATSAPP_ENABLED). While
     * off, no flow waits for a code that cannot arrive: see config/services.php.
     */
    public static function enabled(): bool
    {
        return (bool) config('services.otp.enabled');
    }

    /**
     * Sends a new code for the purpose and keeps it pending with its payload.
     * Returns null when the code is on its way, otherwise a message for the shopper.
     */
    public function start(string $purpose, string $phone, array $payload = []): ?string
    {
        $pending = [
            'purpose' => $purpose,
            'phone' => $phone,
            'client_id' => app(ClientContext::class)->id(),
            'payload' => $payload,
        ];

        return $this->send($pending);
    }

    /** Sends a fresh code for what is already pending. */
    public function resend(): ?string
    {
        $pending = $this->pendingRaw();
        if (! $pending) {
            return 'Your code has expired. Please start again.';
        }
        if (($wait = $this->resendIn()) > 0) {
            return "Please wait {$wait} seconds before asking for a new code.";
        }

        return $this->send($pending);
    }

    /**
     * Checks the entered code. On success the pending code is used up and its details
     * (purpose, phone, payload) are returned; otherwise a message for the shopper.
     *
     * @return array{purpose: string, phone: string, payload: array}|string
     */
    public function verify(string $code): array|string
    {
        $pending = $this->pendingRaw();
        if (! $pending) {
            return 'Your code has expired. Please start again.';
        }
        if (now()->getTimestamp() > $pending['expires_at']) {
            return 'This code has expired. Tap "Resend code" for a new one.';
        }
        if ($pending['attempts'] >= (int) config('services.otp.max_attempts', 5)) {
            return 'Too many wrong attempts. Tap "Resend code" for a new one.';
        }

        if (! hash_equals($pending['code_hash'], $this->hash(trim($code), $pending))) {
            $pending['attempts']++;
            session([self::SESSION_KEY => $pending]);
            $left = (int) config('services.otp.max_attempts', 5) - $pending['attempts'];

            return $left > 0
                ? 'That code is not correct. '.$left.' '.str('attempt')->plural($left).' left.'
                : 'Too many wrong attempts. Tap "Resend code" for a new one.';
        }

        $this->clear();

        return ['purpose' => $pending['purpose'], 'phone' => $pending['phone'], 'payload' => $pending['payload']];
    }

    /**
     * What is pending, for the verify page; null when nothing is (or it belongs to another store).
     *
     * @return array{purpose: string, phone: string, payload: array, expires_at: int, sent_at: int}|null
     */
    public function pending(): ?array
    {
        $pending = $this->pendingRaw();

        return $pending ? array_diff_key($pending, ['code_hash' => true, 'attempts' => true]) : null;
    }

    /** Seconds until another code may be sent; 0 when it may be sent now. */
    public function resendIn(): int
    {
        $pending = $this->pendingRaw();
        if (! $pending) {
            return 0;
        }

        return max(0, $pending['sent_at'] + (int) config('services.otp.resend_after', 60) - now()->getTimestamp());
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /** "******3210": enough for the shopper to recognise their number. */
    public static function mask(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return str_repeat('•', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    // ---- Internals ---------------------------------------------------------

    private function send(array $pending): ?string
    {
        $limiterKey = 'customer-otp:'.$pending['client_id'].':'.$pending['phone'];
        $maxSends = (int) config('services.otp.max_sends', 5);
        if (RateLimiter::tooManyAttempts($limiterKey, $maxSends)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);

            return "Too many codes were sent to this number. Please try again in {$minutes} ".str('minute')->plural($minutes).'.';
        }

        $length = (int) config('services.otp.length', 6);
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        if (! $this->whatsapp->sendTemplate($pending['phone'], 'otp', [$code])) {
            return 'We could not send the code on WhatsApp. Please check the number and try again.';
        }
        RateLimiter::hit($limiterKey, (int) config('services.otp.send_window', 3600));

        $now = now()->getTimestamp();
        $pending['sent_at'] = $now;
        $pending['expires_at'] = $now + (int) config('services.otp.ttl', 300);
        $pending['attempts'] = 0;
        $pending['code_hash'] = $this->hash($code, $pending);
        session([self::SESSION_KEY => $pending]);

        return null;
    }

    /** The pending code, only on the store it was sent from. */
    private function pendingRaw(): ?array
    {
        $pending = session(self::SESSION_KEY);
        if (! is_array($pending) || ! isset($pending['code_hash'])) {
            return null;
        }

        return ($pending['client_id'] ?? null) === app(ClientContext::class)->id() ? $pending : null;
    }

    /** Bound to the purpose, phone and store, so a code cannot be reused for anything else. */
    private function hash(string $code, array $pending): string
    {
        return hash_hmac('sha256', implode('|', [$pending['purpose'], $pending['client_id'], $pending['phone'], $code]), (string) config('app.key'));
    }
}

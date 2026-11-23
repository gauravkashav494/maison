<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends approved WhatsApp templates through the gateway set in config/services.php (whatsapp).
 *
 * A template is named by its key in services.whatsapp.templates ("otp"); the values given
 * fill its {{1}}, {{2}}… placeholders in order. When WhatsApp is switched off nothing leaves
 * the server: the message is logged instead, so sign-up and checkout can be tried locally.
 */
class WhatsApp
{
    /** True when the gateway accepted the message (or WhatsApp is off and it was logged). */
    public function sendTemplate(string $phone, string $template, array $params = []): bool
    {
        $config = config('services.whatsapp');
        $name = $config['templates'][$template] ?? null;
        if (! $name) {
            Log::error("WhatsApp template [{$template}] is not configured.");

            return false;
        }

        $to = $this->recipient($phone, (string) ($config['country_code'] ?? ''));

        if (! ($config['enabled'] ?? false)) {
            Log::info('WhatsApp is disabled; message not sent.', ['to' => $to, 'template' => $name, 'params' => $params]);

            return true;
        }

        $fields = $config['fields'];
        $query = array_merge($config['extra'] ?? [], $config['template_extra'][$template] ?? [], [
            $fields['user'] => $config['user'],
            $fields['password'] => $config['password'],
            $fields['sender'] => $config['sender'],
            $fields['phone'] => $to,
            $fields['template'] => $name,
            $fields['params'] => implode(',', array_map('strval', $params)),
        ]);

        try {
            $response = Http::timeout((int) ($config['timeout'] ?? 10))->get($config['url'], $query);
        } catch (Throwable $e) {
            Log::warning('WhatsApp gateway could not be reached.', ['to' => $to, 'template' => $name, 'error' => $e->getMessage()]);

            return false;
        }

        // The gateway answers 200 with a text body; an error is reported in that body.
        $body = trim($response->body());
        $failed = $response->failed() || preg_match('/\b(error|invalid|failed|insufficient)\b/i', $body);
        if ($failed) {
            Log::warning('WhatsApp gateway refused the message.', ['to' => $to, 'template' => $name, 'status' => $response->status(), 'body' => mb_substr($body, 0, 500)]);

            return false;
        }

        return true;
    }

    /** The number as the gateway wants it: digits only, with the configured country code. */
    private function recipient(string $phone, string $countryCode): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return $countryCode !== '' && strlen($digits) === 10 ? $countryCode.$digits : $digits;
    }
}

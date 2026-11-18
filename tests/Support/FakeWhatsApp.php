<?php

namespace Tests\Support;

use App\Services\WhatsApp;

/** Records WhatsApp messages instead of sending them; set $fails to see the gateway refuse. */
class FakeWhatsApp extends WhatsApp
{
    /** @var array<int, array{phone: string, template: string, params: array}> */
    public array $sent = [];

    public bool $fails = false;

    public function sendTemplate(string $phone, string $template, array $params = []): bool
    {
        if ($this->fails) {
            return false;
        }
        $this->sent[] = ['phone' => $phone, 'template' => $template, 'params' => $params];

        return true;
    }

    /** The code in the last OTP message. */
    public function lastCode(): ?string
    {
        foreach (array_reverse($this->sent) as $message) {
            if ($message['template'] === 'otp') {
                return (string) $message['params'][0];
            }
        }

        return null;
    }
}

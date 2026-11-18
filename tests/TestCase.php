<?php

namespace Tests;

use App\Services\WhatsApp;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeWhatsApp;

abstract class TestCase extends BaseTestCase
{
    protected FakeWhatsApp $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();

        // No test talks to the WhatsApp gateway; codes sent are kept here to be entered.
        $this->whatsapp = new FakeWhatsApp;
        $this->app->instance(WhatsApp::class, $this->whatsapp);
    }

    /** Enters the WhatsApp code that was sent last (or the code given). */
    protected function enterOtp(?string $code = null, array $session = []): TestResponse
    {
        return $this->withSession($session)->post('/verify', ['code' => $code ?? $this->whatsapp->lastCode()]);
    }
}

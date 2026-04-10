<?php

namespace Tests\Feature;

use Tests\TestCase;

class TelephonyWebhookSurfaceTest extends TestCase
{
    public function test_legacy_twilio_and_exotel_webhook_endpoints_are_not_exposed(): void
    {
        $endpoints = [
            ['POST', '/twilio/voice'],
            ['POST', '/twilio/status'],
            ['POST', '/twilio/recording'],
            ['POST', '/exotel/outgoing'],
            ['POST', '/exotel/webhook'],
            ['POST', '/exotel/voip-call'],
            ['POST', '/exotel/browser-incoming'],
            ['GET',  '/exotel/incoming-poll'],
            ['POST', '/webhook/incoming-call'],
            ['POST', '/webhook/call-status'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->call($method, $uri);
            $response->assertNotFound();
        }
    }
}


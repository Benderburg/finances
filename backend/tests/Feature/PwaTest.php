<?php

namespace Tests\Feature;

use Tests\TestCase;

final class PwaTest extends TestCase
{
    public function test_public_pwa_resources_revalidate_without_session_cookies(): void
    {
        foreach (['sw.js' => 'application/javascript', 'manifest.webmanifest' => 'application/manifest+json'] as $asset => $type) {
            $response = $this->get('/'.$asset);
            $response->assertOk()->assertHeader('Service-Worker-Allowed', '/');
            $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
            $this->assertStringStartsWith($type, $response->headers->get('Content-Type'));
            $this->assertFalse($response->headers->has('Set-Cookie'));
        }
    }
}

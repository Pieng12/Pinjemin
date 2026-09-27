<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_assets_use_https_when_the_request_is_forwarded_by_railway(): void
    {
        config()->set('features.marketplace_enabled', false);

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'pinjemin-production.up.railway.app',
            'HTTP_X_FORWARDED_HOST' => 'pinjemin-production.up.railway.app',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'REMOTE_ADDR' => '10.0.0.10',
        ])->get('/');

        $response
            ->assertOk()
            ->assertSee('https://pinjemin-production.up.railway.app/build/assets/', false)
            ->assertSee('https://pinjemin-production.up.railway.app/images/landing/logo pinjemin.svg', false)
            ->assertDontSee('http://pinjemin-production.up.railway.app/', false);
    }
}

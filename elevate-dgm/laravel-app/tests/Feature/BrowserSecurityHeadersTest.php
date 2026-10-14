<?php

namespace Tests\Feature;

use App\Http\Middleware\BrowserSecurityHeaders;
use Illuminate\Http\Request;
use Tests\TestCase;

class BrowserSecurityHeadersTest extends TestCase
{
    public function test_auth_responses_cannot_be_cached_and_have_compatible_security_headers(): void
    {
        $response = (new BrowserSecurityHeaders)->handle(Request::create('http://localhost/login/otp'), fn () => response('test'));

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("form-action 'self'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_production_redirects_http_without_executing_the_request(): void
    {
        $this->app->instance('env', 'production');
        $response = (new BrowserSecurityHeaders)->handle(Request::create('http://localhost/login'), function () {
            $this->fail('Insecure production request reached the application.');
        });

        $this->assertSame(308, $response->getStatusCode());
        $this->assertSame('https://localhost/login', $response->headers->get('Location'));
    }

    public function test_production_https_responses_send_hsts(): void
    {
        $this->app->instance('env', 'production');
        $response = (new BrowserSecurityHeaders)->handle(Request::create('https://localhost/login'), fn () => response('test'));

        $this->assertSame('max-age=31536000', $response->headers->get('Strict-Transport-Security'));
    }
}

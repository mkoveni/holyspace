<?php

declare(strict_types=1);

namespace App\Tests\Shared\Presentation;

use App\Tests\Support\IdentityApiTestCase;

/** Every `/api` response carries Spring Security's default headers, as every Java response does. */
final class SecurityHeadersApiTest extends IdentityApiTestCase
{
    private function assertSpringHeaders(string $uri, ?string $token, int $status): void
    {
        $this->call('GET', $uri, $token);
        $headers = $this->client->getResponse()->headers;
        self::assertSame($status, $this->code(), $uri);
        foreach (['no-cache', 'no-store', 'must-revalidate'] as $directive) {
            self::assertTrue($headers->hasCacheControlDirective($directive), "$uri: ".$headers->get('Cache-Control'));
        }
        self::assertSame('0', $headers->getCacheControlDirective('max-age'), $uri);
        self::assertSame('no-cache', $headers->get('Pragma'), $uri);
        self::assertSame('0', $headers->get('Expires'), $uri);
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'), $uri);
        self::assertSame('DENY', $headers->get('X-Frame-Options'), $uri);
        self::assertSame('0', $headers->get('X-XSS-Protection'), $uri);
        self::assertFalse($headers->has('Strict-Transport-Security'), "$uri: HSTS is only sent over HTTPS");
    }

    public function testSuccessesAndFailuresInTwoContextsCarrySpringSecurityHeaders(): void
    {
        $this->account('admin', administrator: true);
        $admin = $this->login('admin');
        $this->accountWith('member', []);
        $member = $this->login('member');
        $missing = '00000000-0000-4000-8000-000000000001';

        $this->assertSpringHeaders('/api/people', $admin, 200);
        $this->assertSpringHeaders('/api/people', null, 401);
        $this->assertSpringHeaders('/api/people', $member, 403);
        $this->assertSpringHeaders("/api/people/$missing", $admin, 404);

        $this->assertSpringHeaders('/api/giving/funds', $admin, 200);
        $this->assertSpringHeaders('/api/giving/funds', null, 401);
        $this->assertSpringHeaders('/api/giving/funds', $member, 403);
        $this->assertSpringHeaders("/api/giving/gifts/$missing", $admin, 404);

        $this->assertSpringHeaders('/api/no-such-route', $admin, 404);
    }

    public function testControllerCacheHeadersWinLikeSpringsCacheControlWriter(): void
    {
        $this->account('admin', administrator: true);
        $this->call('GET', '/api/me/sessions', $this->login('admin'));
        $headers = $this->client->getResponse()->headers;

        self::assertSame(200, $this->code());
        self::assertTrue($headers->hasCacheControlDirective('no-store'));
        self::assertFalse($headers->hasCacheControlDirective('must-revalidate'), (string) $headers->get('Cache-Control'));
        self::assertFalse($headers->has('Pragma'));
        self::assertFalse($headers->has('Expires'));
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
    }

    public function testHstsIsSentOverHttps(): void
    {
        $this->client->request('GET', '/api/people', server: ['HTTPS' => 'on', 'HTTP_ACCEPT' => 'application/json']);

        self::assertSame(401, $this->code());
        self::assertSame('max-age=31536000 ; includeSubDomains', $this->client->getResponse()->headers->get('Strict-Transport-Security'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Giving\Presentation;

use App\Tests\Support\IdentityApiTestCase;

/** Financial responses, errors included, are never stored by a browser or proxy (Java: Spring Security's default headers, applied by the Shared `SecurityHeadersListener`). */
final class GivingNoStoreApiTest extends IdentityApiTestCase
{
    private function assertNotStored(string $method, string $uri, ?string $token, int $status): void
    {
        $this->call($method, $uri, $token);
        $response = $this->client->getResponse();
        self::assertSame($status, $response->getStatusCode(), $uri);
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'), "$uri: ".$response->headers->get('Cache-Control'));
    }

    public function testEveryGivingResponseForbidsStorage(): void
    {
        $this->account('admin', administrator: true);
        $admin = $this->login('admin');
        $this->accountWith('member', []);
        $member = $this->login('member');

        $this->assertNotStored('GET', '/api/giving/gifts?from=2026-01-01&to=2026-12-31', $admin, 200);
        $this->assertNotStored('GET', '/api/giving/funds', $admin, 200);
        $this->assertNotStored('GET', '/api/giving/pledges', $admin, 200);
        $this->assertNotStored('GET', '/api/giving/totals?from=2026-01-01&to=2026-12-31', $admin, 200);
        $this->assertNotStored('GET', '/api/giving/statements/00000000-0000-4000-8000-000000000001?from=2026-01-01&to=2026-12-31', $admin, 200);
        $this->assertNotStored('GET', '/api/giving/gifts/00000000-0000-4000-8000-000000000001', $admin, 404);
        $this->assertNotStored('GET', '/api/giving/gifts?from=2026-01-01&to=2026-12-31', $member, 403);
        $this->assertNotStored('GET', '/api/giving/gifts', null, 401);

        $response = $this->client->getResponse();
        self::assertSame('no-cache', $response->headers->get('Pragma'));
        self::assertSame('0', $response->headers->get('Expires'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Presentation;

use App\Tests\Support\IdentityApiTestCase;

final class DeviceSessionsApiTest extends IdentityApiTestCase
{
    private string $mobile;
    private string $web;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account('member');
        $this->call('POST', '/api/login_check', null, ['username' => 'member', 'password' => self::PASSWORD, 'deviceName' => 'Holyspace · Android']);
        $this->mobile = (string) $this->json()['token'];
        $this->web = $this->login('member');
    }

    private function sessionId(string $token): string
    {
        return (string) $this->connection->fetchOne('SELECT id FROM account_sessions WHERE token_hash = ?', [hash('sha256', $token)]);
    }

    public function testSessionsAreScopedRedactedAndMarkTheCurrentDevice(): void
    {
        $this->call('GET', '/api/me/sessions', $this->mobile);

        self::assertSame(200, $this->code());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $items = $this->json()['items'];
        self::assertEqualsCanonicalizing(['Holyspace · Android', 'Web browser'], array_column($items, 'deviceName'));
        self::assertSame(['Holyspace · Android'], array_column(array_filter($items, static fn (array $item): bool => $item['current']), 'deviceName'));
        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString($this->mobile, $body);
        self::assertStringNotContainsString($this->web, $body);
        self::assertStringNotContainsString('token', $body);
    }

    public function testAMemberCanRevokeAnotherDeviceButNotSomeoneElsesSession(): void
    {
        $this->account('admin', administrator: true);
        $adminSession = $this->sessionId($this->login('admin'));

        $this->call('DELETE', '/api/me/sessions/'.$this->sessionId($this->web), $this->mobile);
        self::assertSame(204, $this->code());
        $this->call('GET', '/api/me', $this->web);
        self::assertSame(401, $this->code(), 'the revoked device is signed out at once');
        $this->call('GET', '/api/me', $this->mobile);
        self::assertSame(200, $this->code());

        $this->call('DELETE', "/api/me/sessions/$adminSession", $this->mobile);
        self::assertSame(404, $this->code());
        self::assertSame(1, $this->rows('account_sessions', "id = '$adminSession'"));
        self::assertSame(1, $this->rows('identity_access_audit', "action = 'session.revoked'"));
    }

    public function testRevokingTheCurrentSessionSignsThisDeviceOut(): void
    {
        $this->call('DELETE', '/api/me/sessions/'.$this->sessionId($this->mobile), $this->mobile);
        self::assertSame(204, $this->code());

        $this->call('GET', '/api/me/sessions', $this->mobile);
        self::assertSame(401, $this->code());
    }

    public function testPagingAndIdentifiersAreValidated(): void
    {
        $this->call('GET', '/api/me/sessions?limit=1', $this->mobile);
        self::assertCount(1, $this->json()['items']);
        self::assertTrue($this->json()['hasMore']);

        foreach (['limit=0', 'limit=101', 'offset=-1', 'offset=100001'] as $query) {
            $this->call('GET', "/api/me/sessions?$query", $this->mobile);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('DELETE', '/api/me/sessions/nope', $this->mobile);
        self::assertSame(400, $this->code());
        $this->call('GET', '/api/me/sessions');
        self::assertSame(401, $this->code());
    }

    public function testExpiredSessionsAreNotListed(): void
    {
        $this->connection->executeStatement("UPDATE account_sessions SET expires_at = now() - interval '1 minute' WHERE id = ?", [$this->sessionId($this->web)]);

        $this->call('GET', '/api/me/sessions', $this->mobile);

        self::assertSame(['Holyspace · Android'], array_column($this->json()['items'], 'deviceName'));
    }
}

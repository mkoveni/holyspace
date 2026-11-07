<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Presentation;

use App\Tests\Support\IdentityApiTestCase;

final class AuthenticationApiTest extends IdentityApiTestCase
{
    public function testLoginReturnsOpaqueTokenAndMeShowsLivePermissions(): void
    {
        $this->account('grace', administrator: true);

        $token = $this->login('grace');

        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $token);
        $this->call('GET', '/api/me', $token);
        self::assertSame(200, $this->code());
        $me = $this->json();
        self::assertSame('grace', $me['username']);
        self::assertSame(['administrator'], $me['roles']);
        self::assertContains('identity.manage', $me['permissions']);
        self::assertContains('identity.read', $me['permissions'], 'identity.read is implied by identity.manage');
        self::assertContains('volunteering.serve', $me['permissions'], 'working permissions are implied by management grants');
        self::assertSame($me['permissions'], array_values(array_unique((array) $me['permissions'])));
        self::assertNull($me['personId']);
        self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T[\d:.]+Z\z/', $me['createdAt']);
    }

    public function testOnlyTheTokenHashIsStored(): void
    {
        $this->account('grace');
        $token = $this->login('grace');

        $stored = $this->connection->fetchOne('SELECT token_hash FROM account_sessions');

        self::assertSame(hash('sha256', $token), $stored);
        self::assertNotSame($token, $stored);
    }

    public function testLoginAcceptsEmailAndRecordsDeviceName(): void
    {
        $this->account('grace');

        $this->call('POST', '/api/login_check', null, ['username' => 'GRACE@example.test', 'password' => self::PASSWORD, 'deviceName' => ' Pixel 9 ']);

        self::assertSame(200, $this->code());
        self::assertSame('Pixel 9', $this->connection->fetchOne('SELECT device_name FROM account_sessions'));
    }

    public function testWrongPasswordUnknownUserAndDisabledAccountAreIndistinguishable(): void
    {
        $this->account('grace');
        $this->account('mothballed', status: 'disabled');

        foreach ([['grace', 'wrong password!!'], ['nobody', self::PASSWORD], ['mothballed', self::PASSWORD]] as [$user, $password]) {
            $this->call('POST', '/api/login_check', null, ['username' => $user, 'password' => $password]);
            self::assertSame(401, $this->code());
            self::assertSame('Invalid username or password.', $this->message());
        }
        self::assertSame(0, $this->rows('account_sessions'));
    }

    public function testFifthFailureThrottlesEvenTheCorrectPasswordAndTheCounterIsCommitted(): void
    {
        $this->account('grace');

        for ($i = 0; $i < 5; ++$i) {
            $this->call('POST', '/api/login_check', null, ['username' => 'grace', 'password' => 'wrong password!!']);
            self::assertSame(401, $this->code());
        }
        self::assertSame(5, (int) $this->connection->fetchOne('SELECT failures FROM login_attempts'), 'failure counters survive the rejected login');

        $this->call('POST', '/api/login_check', null, ['username' => 'grace', 'password' => self::PASSWORD]);
        self::assertSame(429, $this->code());
        self::assertSame('60', $this->client->getResponse()->headers->get('Retry-After'));
        self::assertSame(0, $this->rows('account_sessions'));
    }

    public function testThrottleWindowExpiresAndSuccessClearsFailures(): void
    {
        $this->account('grace');
        for ($i = 0; $i < 5; ++$i) {
            $this->call('POST', '/api/login_check', null, ['username' => 'grace', 'password' => 'wrong password!!']);
        }
        $this->connection->executeStatement("UPDATE login_attempts SET resets_at = now() - interval '1 second'");

        $this->login('grace');

        self::assertSame(0, $this->rows('login_attempts'));
    }

    public function testFailuresAfterAnExpiredWindowRestartTheCounter(): void
    {
        $this->account('grace');
        for ($i = 0; $i < 3; ++$i) {
            $this->call('POST', '/api/login_check', null, ['username' => 'grace', 'password' => 'wrong password!!']);
        }
        $this->connection->executeStatement("UPDATE login_attempts SET resets_at = now() - interval '1 second'");

        $this->call('POST', '/api/login_check', null, ['username' => 'grace', 'password' => 'wrong password!!']);

        self::assertSame(1, (int) $this->connection->fetchOne('SELECT failures FROM login_attempts'));
    }

    public function testMalformedLoginRequestsAreBadRequests(): void
    {
        foreach ([[], ['username' => 'grace'], ['username' => '', 'password' => 'x'], ['username' => 'g', 'password' => str_repeat('x', 73)]] as $body) {
            $this->call('POST', '/api/login_check', null, $body);
            self::assertSame(400, $this->code(), json_encode($body, JSON_THROW_ON_ERROR));
            self::assertSame('Invalid request. Check the required fields and their formats.', $this->message());
        }
    }

    public function testProtectedRoutesRequireAValidSession(): void
    {
        $this->account('grace');
        $expired = $this->login('grace');
        $this->connection->executeStatement("UPDATE account_sessions SET expires_at = now() - interval '1 minute'");

        foreach ([null, 'not-a-token', str_repeat('A', 43), $expired] as $token) {
            $this->call('GET', '/api/me', $token);
            self::assertSame(401, $this->code());
            self::assertSame('Authentication required.', $this->message());
            self::assertSame('Bearer', $this->client->getResponse()->headers->get('WWW-Authenticate'));
        }
    }

    public function testLogoutRevokesOnlyTheCurrentSession(): void
    {
        $this->account('grace');
        $first = $this->login('grace');
        $second = $this->login('grace');

        $this->call('POST', '/api/logout', $first);
        self::assertSame(204, $this->code());

        $this->call('GET', '/api/me', $first);
        self::assertSame(401, $this->code());
        $this->call('GET', '/api/me', $second);
        self::assertSame(200, $this->code());
        self::assertSame(1, $this->rows('identity_access_audit', "action = 'session.logged_out'"));
    }

    public function testChangingMyPasswordNeedsTheCurrentPasswordAndRevokesEverySession(): void
    {
        $id = $this->account('grace');
        $first = $this->login('grace');
        $second = $this->login('grace');

        $this->call('PUT', '/api/me/password', $first, ['currentPassword' => 'not my password', 'password' => 'a brand new password']);
        self::assertSame(403, $this->code());
        self::assertSame('Current password is incorrect.', $this->message());

        $this->call('PUT', '/api/me/password', $first, ['currentPassword' => self::PASSWORD, 'password' => 'a brand new password']);
        self::assertSame(204, $this->code());

        foreach ([$first, $second] as $token) {
            $this->call('GET', '/api/me', $token);
            self::assertSame(401, $this->code());
        }
        self::assertNotSame('', $this->login('grace', 'a brand new password'));
        self::assertSame(1, $this->rows('identity_access_audit', "action = 'account.password_changed' AND target_id = '$id'"));
    }

    public function testNewPasswordMustMeetPolicy(): void
    {
        $this->account('grace');
        $token = $this->login('grace');

        $this->call('PUT', '/api/me/password', $token, ['currentPassword' => self::PASSWORD, 'password' => 'too short']);

        self::assertSame(400, $this->code());
    }

    public function testDisabledAccountsLoseTheirSessionImmediately(): void
    {
        $id = $this->account('grace');
        $token = $this->login('grace');
        $this->connection->update('user_accounts', ['status' => 'disabled'], ['id' => $id]);

        $this->call('GET', '/api/me', $token);

        self::assertSame(401, $this->code());
    }
}

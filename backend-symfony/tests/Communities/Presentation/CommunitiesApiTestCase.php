<?php

declare(strict_types=1);

namespace App\Tests\Communities\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Uid\Uuid;

/** Shared setup for Communities API tests: an administrator, people, communities and memberships. */
abstract class CommunitiesApiTestCase extends IdentityApiTestCase
{
    protected const string ROOT = '/api/communities';

    protected string $admin;

    protected string $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminId = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
    }

    protected function person(string $firstName): string
    {
        $this->call('POST', '/api/people', $this->admin, ['person' => ['firstName' => $firstName, 'lastName' => 'Mabasa']]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    protected function community(string $kind, string $name, ?string $parent = null): string
    {
        $this->call('POST', self::ROOT, $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'kind' => $kind, 'name' => $name, 'parentId' => $parent]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    protected function join(string $community, string $person, ?string $token = null): string
    {
        $this->call('POST', self::ROOT."/$community/members", $token ?? $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $person]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** A login with ministries.lead appointed to lead the given membership; returns its token. */
    protected function leader(string $username, string $membership): string
    {
        $account = $this->accountWith($username, ['ministries.lead']);
        $this->call('POST', self::ROOT."/memberships/$membership/leadership", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0, 'role' => 'LEADER', 'accountId' => $account, 'reason' => 'Verified login identity']);
        self::assertSame(201, $this->code(), $this->message());

        return $this->login($username);
    }

    protected function body(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }
}

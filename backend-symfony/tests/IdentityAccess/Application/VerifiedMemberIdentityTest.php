<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Application;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\Actor;
use App\IdentityAccess\Application\Contract\CurrentActor;
use App\IdentityAccess\Application\VerifiedMemberIdentity;
use App\IdentityAccess\Infrastructure\Persistence\DbalAccessStore;
use App\IdentityAccess\Infrastructure\Persistence\DbalAccountStore;
use App\IdentityAccess\Infrastructure\Persistence\DbalMemberLinkStore;
use App\IdentityAccess\Infrastructure\Persistence\DbalSessionStore;
use App\People\Infrastructure\Persistence\DbalPersonStore;
use App\People\Infrastructure\PublishedPersonDirectory;
use App\Shared\Infrastructure\Persistence\AdvisoryLock;
use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Clock\NativeClock;
use Throwable;

/** Member identity against the real schema: an account only ever resolves to its own, active, available person. */
final class VerifiedMemberIdentityTest extends IdentityApiTestCase
{
    private string $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
    }

    private function identityFor(?Actor $actor = null): VerifiedMemberIdentity
    {
        $people = new DbalPersonStore($this->connection);
        $sessions = new DbalSessionStore($this->connection);
        $accounts = new DbalAccountStore($this->connection);
        $currentActor = new class ($actor) implements CurrentActor {
            public function __construct(private readonly ?Actor $actor)
            {
            }

            public function current(): ?Actor
            {
                return $this->actor;
            }
        };
        $links = new DbalMemberLinkStore($this->connection);

        return new VerifiedMemberIdentity(
            new Authorization($currentActor, $accounts, new DbalAccessStore($this->connection, new AdvisoryLock($this->connection)), $sessions, new NativeClock()),
            $links,
            $links,
            new PublishedPersonDirectory($people, $people),
        );
    }

    private function link(string $account, string $firstName = 'Solly'): string
    {
        $this->call('POST', '/api/people', $this->admin, ['person' => ['firstName' => $firstName, 'lastName' => 'Mabasa']]);
        $person = (string) $this->json()['id'];
        $this->call('PUT', "/api/member-access/$account", $this->admin, ['personId' => $person, 'active' => true, 'expectedVersion' => 0, 'evidence' => 'Verified']);
        self::assertSame(204, $this->code());

        return $person;
    }

    public function testAnUnlinkedAccountHasNoPerson(): void
    {
        $account = $this->account('member');

        self::assertNull($this->identityFor()->activePerson($account));
        self::assertNull($this->identityFor()->boundPerson($account));
    }

    public function testALinkedAccountResolvesToItsPerson(): void
    {
        $account = $this->account('member');
        $person = $this->link($account);

        self::assertSame($person, $this->identityFor()->activePerson($account));
        self::assertSame($person, $this->identityFor()->boundPerson($account));
    }

    public function testARevokedLinkStillBindsButNoLongerActs(): void
    {
        $account = $this->account('member');
        $person = $this->link($account);
        $this->call('PUT', "/api/member-access/$account", $this->admin, ['personId' => $person, 'active' => false, 'expectedVersion' => 1, 'evidence' => 'Left']);

        self::assertNull($this->identityFor()->activePerson($account));
        self::assertSame($person, $this->identityFor()->boundPerson($account), 'the binding history is kept so financial identity stays consistent');
    }

    public function testAnArchivedPersonCannotAct(): void
    {
        $account = $this->account('member');
        $person = $this->link($account);
        $this->call('PUT', "/api/people/$person/archive", $this->admin, ['expectedVersion' => 0, 'archived' => true]);

        self::assertNull($this->identityFor()->activePerson($account));
        self::assertSame($person, $this->identityFor()->boundPerson($account));
    }

    public function testRequiringAPersonChecksTheCallerAndTheLink(): void
    {
        $account = $this->account('member');
        $other = $this->account('other');
        $token = $this->login('member');
        $sessionId = (string) $this->connection->fetchOne('SELECT id FROM account_sessions WHERE token_hash = ?', [hash('sha256', $token)]);
        $actor = new Actor($account, $sessionId);

        try {
            $this->identityFor($actor)->requirePerson($account);
            self::fail('An unverified member must be told to ask the office.');
        } catch (Throwable $error) {
            self::assertSame('Ask the church office to verify your member account.', $error->getMessage());
        }

        $person = $this->link($account);
        self::assertSame($person, $this->identityFor($actor)->requirePerson($account));

        try {
            $this->identityFor($actor)->requirePerson($other);
            self::fail('A claimed actor that is not the caller must be refused.');
        } catch (Throwable $error) {
            self::assertSame('Permission denied.', $error->getMessage());
        }
    }
}

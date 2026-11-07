<?php

declare(strict_types=1);

namespace App\Tests\Giving\Presentation;

use App\Giving\Application\Contract\DonorIdentity;
use App\Giving\Application\Contract\DonorIdentityGuard;
use App\Giving\Application\Contract\OnlineFundDirectory;
use App\Giving\Application\Contract\VerifiedDonor;
use App\Giving\Domain\GivingException;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Infrastructure\Security\SessionUser;
use App\Shared\Application\UnitOfWork;
use App\Shared\Domain\ErrorKind;
use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Uid\Uuid;

/**
 * The verified-identity contracts Payments will call for the live caller (`DonorIdentity`, `DonorIdentityGuard`) and the
 * online fund catalogue (`OnlineFundDirectory`), over real accounts, sessions and donor links. Financial identity comes only
 * from an active, verified donor link: never from permissions, email or People records.
 */
final class DonorIdentityContractTest extends IdentityApiTestCase
{
    private string $admin;
    private string $adminAccount;
    private string $donorAccount;
    private string $donor;
    private string $other;
    private string $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminAccount = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
        $this->donorAccount = $this->account('donor');
        $this->donor = $this->person('Donor');
        $this->other = $this->person('Other');
        $this->call('POST', '/api/giving/funds', $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'name' => 'General']);
        self::assertSame(201, $this->code(), $this->message());
        $this->fund = (string) $this->json()['id'];
    }

    private function person(string $firstName): string
    {
        $this->call('POST', '/api/people', $this->admin, ['person' => ['firstName' => $firstName, 'lastName' => 'Mabasa']]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function link(bool $active, int $version): void
    {
        $this->call('PUT', "/api/giving/donor-access/{$this->donorAccount}", $this->admin, ['personId' => $this->donor, 'active' => $active, 'expectedVersion' => $version, 'evidence' => 'Verified account holder identity in person']);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
    }

    /** Authenticates the session behind $token for calls outside a request, as the firewall does for a Payments request. */
    private function signIn(?string $token): void
    {
        $storage = self::getContainer()->get('security.token_storage');
        \assert($storage instanceof TokenStorageInterface);
        if (null === $token) {
            $storage->setToken(null);

            return;
        }
        $session = (string) $this->connection->fetchOne('SELECT id FROM account_sessions WHERE token_hash = ?', [hash('sha256', $token)]);
        $account = (string) $this->connection->fetchOne('SELECT account_id FROM account_sessions WHERE id = ?', [$session]);
        $storage->setToken(new PostAuthenticationToken(new SessionUser($account, $session, []), 'main', []));
    }

    private function identity(): DonorIdentity
    {
        $identity = self::getContainer()->get(DonorIdentity::class);
        \assert($identity instanceof DonorIdentity);

        return $identity;
    }

    private function directory(): OnlineFundDirectory
    {
        $directory = self::getContainer()->get(OnlineFundDirectory::class);
        \assert($directory instanceof OnlineFundDirectory);

        return $directory;
    }

    /** Runs the guard in a transaction, as a Payments command handler on the bus does. */
    private function lockOwn(string $actor, string $donor): void
    {
        $guard = self::getContainer()->get(DonorIdentityGuard::class);
        \assert($guard instanceof DonorIdentityGuard);
        $transaction = self::getContainer()->get(UnitOfWork::class);
        \assert($transaction instanceof UnitOfWork);
        $transaction->execute(static fn () => $guard->lockOwn($actor, $donor));
    }

    private static function forbidden(callable $call): void
    {
        try {
            $call();
            self::fail('Expected verified donor access to be refused.');
        } catch (GivingException $error) {
            self::assertSame([ErrorKind::Forbidden, 'Verified donor access is required.'], [$error->kind(), $error->getMessage()]);
        }
    }

    public function testAnUnauthenticatedCallerHasNoFinancialIdentity(): void
    {
        $this->signIn(null);

        $this->expectException(IdentityException::class);
        $this->expectExceptionMessage('A valid authenticated session is required.');

        $this->identity()->current();
    }

    public function testAVerifiedLinkIsTheOnlySourceOfTheCallersDonorIdentity(): void
    {
        $this->link(true, 0);
        $this->signIn($this->login('donor'));

        self::assertEquals(new VerifiedDonor($this->donor, 'Donor Mabasa'), $this->identity()->current());
        self::assertSame($this->donor, $this->identity()->requireOwn($this->donorAccount, null));
        self::assertSame($this->donor, $this->identity()->requireOwn($this->donorAccount, $this->donor));
        $this->lockOwn($this->donorAccount, $this->donor);

        self::forbidden(fn () => $this->identity()->requireOwn($this->donorAccount, $this->other));
        self::forbidden(fn () => $this->identity()->requireOwn($this->adminAccount, null));
        self::forbidden(fn () => $this->lockOwn($this->donorAccount, $this->other));
        self::forbidden(fn () => $this->lockOwn($this->adminAccount, $this->donor));
    }

    public function testFinancialPermissionsDoNotGrantADonorIdentity(): void
    {
        $this->link(true, 0);
        $this->signIn($this->admin);

        self::assertNull($this->identity()->current(), 'giving.manage is not a donor link');
        self::forbidden(fn () => $this->identity()->requireOwn($this->adminAccount, $this->donor));
        self::forbidden(fn () => $this->lockOwn($this->adminAccount, $this->donor));
    }

    public function testRevocationEndsTheIdentityWithoutReassigningIt(): void
    {
        $this->link(true, 0);
        $this->link(false, 1);
        $this->signIn($this->login('donor'));

        self::assertNull($this->identity()->current());
        self::forbidden(fn () => $this->identity()->requireOwn($this->donorAccount, null));
        self::forbidden(fn () => $this->lockOwn($this->donorAccount, $this->donor));
    }

    public function testOnlyActiveFundsExplicitlyOfferedAreAvailableOnline(): void
    {
        self::assertSame([], $this->directory()->funds(false), 'active funds are not offered online automatically');
        self::assertFalse($this->directory()->enabled($this->fund));
        self::assertSame([[$this->fund, true, false]], array_map(static fn ($fund): array => [$fund->id, $fund->active, $fund->enabled], $this->directory()->funds(true)));

        $this->call('PUT', "/api/giving/online-funds/{$this->fund}", $this->admin, ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
        self::assertTrue($this->directory()->enabled($this->fund));
        self::assertSame([$this->fund], array_map(static fn ($fund): string => $fund->id, $this->directory()->funds(false)));

        $this->call('PUT', "/api/giving/funds/{$this->fund}", $this->admin, ['expectedVersion' => 1, 'name' => 'General', 'active' => false, 'reason' => 'Closed']);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
        self::assertFalse($this->directory()->enabled($this->fund), 'an archived fund is never offered, even while enabled');
        self::assertSame([], $this->directory()->funds(false));
        self::assertFalse($this->directory()->enabled(Uuid::v4()->toRfc4122()), 'an unknown fund is not enabled');
    }
}

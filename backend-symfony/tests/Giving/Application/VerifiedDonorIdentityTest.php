<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Contract\VerifiedDonor;
use App\Giving\Application\VerifiedDonorIdentity;
use App\Giving\Domain\GivingException;
use App\Giving\Infrastructure\DonorPersonBindingPolicy;
use PHPUnit\Framework\TestCase;

/** Financial identity from verified links only, and the binding veto IdentityAccess collects. */
final class VerifiedDonorIdentityTest extends TestCase
{
    private const string ACCOUNT = '00000000-0000-4000-8000-0000000000a1';
    private const string PERSON = '00000000-0000-4000-8000-0000000000d1';
    private const string OTHER = '00000000-0000-4000-8000-0000000000d2';

    private LockLog $log;
    private InMemoryDonorLinks $links;
    private FakePeople $people;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->links = new InMemoryDonorLinks($this->log);
        $this->people = new FakePeople($this->log);
        $this->people->add(self::PERSON);
    }

    private function identity(string $caller = self::ACCOUNT): VerifiedDonorIdentity
    {
        return new VerifiedDonorIdentity(new FixedAccount($caller), $this->links, $this->people, $this->links);
    }

    private function forbidden(callable $action): void
    {
        try {
            $action();
            self::fail('donor access was granted');
        } catch (GivingException $error) {
            self::assertSame('Verified donor access is required.', $error->getMessage());
        }
    }

    public function testOnlyAnActiveLinkToAnAvailablePersonIsADonor(): void
    {
        self::assertNull($this->identity()->current(), 'no link, no donor: matching emails or households grant nothing');
        $this->links->add(self::ACCOUNT, self::PERSON, false);
        self::assertNull($this->identity()->current(), 'a revoked link');
        $this->links->add(self::ACCOUNT, self::PERSON);
        self::assertEquals(new VerifiedDonor(self::PERSON, 'Donor Mabasa'), $this->identity()->current());
        $this->people->add(self::PERSON, false);
        self::assertNull($this->identity()->current(), 'an unavailable person');
    }

    public function testRequireOwnChecksTheCallerAndTheDonor(): void
    {
        $this->links->add(self::ACCOUNT, self::PERSON);

        self::assertSame(self::PERSON, $this->identity()->requireOwn(self::ACCOUNT, null));
        self::assertSame(self::PERSON, $this->identity()->requireOwn(self::ACCOUNT, self::PERSON));
        $this->forbidden(fn () => $this->identity()->requireOwn(self::ACCOUNT, self::OTHER));
        $this->forbidden(fn () => $this->identity('00000000-0000-4000-8000-0000000000a9')->requireOwn(self::ACCOUNT, null));
        $this->links->links = [];
        $this->forbidden(fn () => $this->identity()->requireOwn(self::ACCOUNT, null));
    }

    public function testLockOwnLocksTheLinkFirstAndRequiresItToBeActiveAndOwn(): void
    {
        $this->links->add(self::ACCOUNT, self::PERSON);
        $this->identity()->lockOwn(self::ACCOUNT, self::PERSON);
        self::assertSame(['link:'.self::ACCOUNT], $this->log->locks, 'the guard takes no People lock, so callers lock People after it');

        $this->forbidden(fn () => $this->identity()->lockOwn(self::ACCOUNT, self::OTHER));
        $this->forbidden(fn () => $this->identity('00000000-0000-4000-8000-0000000000a9')->lockOwn(self::ACCOUNT, self::PERSON));
        $this->links->add(self::ACCOUNT, self::PERSON, false);
        $this->forbidden(fn () => $this->identity()->lockOwn(self::ACCOUNT, self::PERSON));
    }

    public function testTheBindingPolicyVetoesAMemberLinkThatContradictsTheDonorLink(): void
    {
        $policy = new DonorPersonBindingPolicy($this->links);
        self::assertTrue($policy->permits(self::ACCOUNT, self::OTHER), 'no donor link, no veto');
        $this->links->add(self::ACCOUNT, self::PERSON, false);
        self::assertTrue($policy->permits(self::ACCOUNT, self::PERSON));
        self::assertFalse($policy->permits(self::ACCOUNT, self::OTHER), 'a revoked donor link still binds the person');
    }
}

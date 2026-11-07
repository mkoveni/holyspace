<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Infrastructure\PublishedDonorCheckoutGuard;
use PHPUnit\Framework\TestCase;

/** The one-time Payments handoff guard: only an active link of that account to that available person is eligible. */
final class PublishedDonorCheckoutGuardTest extends TestCase
{
    private const string ACCOUNT = '00000000-0000-4000-8000-0000000000a1';
    private const string DONOR = '00000000-0000-4000-8000-0000000000dd';
    private const string OTHER = '00000000-0000-4000-8000-0000000000de';

    private LockLog $log;
    private InMemoryDonorLinks $links;
    private FakePeople $people;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->links = new InMemoryDonorLinks($this->log);
        $this->people = new FakePeople($this->log);
        $this->people->add(self::DONOR);
        $this->people->add(self::OTHER);
    }

    private function eligible(string $donor = self::DONOR): bool
    {
        return new PublishedDonorCheckoutGuard($this->links, $this->people)->lockEligible(self::ACCOUNT, $donor);
    }

    public function testAnActiveLinkToAnAvailablePersonIsEligibleAndLocksOnlyTheLink(): void
    {
        $this->links->add(self::ACCOUNT, self::DONOR);

        self::assertTrue($this->eligible());
        self::assertSame(['link:'.self::ACCOUNT], $this->log->locks, 'the person is read, not locked, so People locks still follow');
    }

    public function testEveryOtherCaseIsIneligible(): void
    {
        self::assertFalse($this->eligible(), 'no link');
        $this->links->add(self::ACCOUNT, self::DONOR, active: false);
        self::assertFalse($this->eligible(), 'a revoked link');
        $this->links->add(self::ACCOUNT, self::DONOR);
        self::assertFalse($this->eligible(self::OTHER), 'another person');
        $this->people->add(self::DONOR, available: false);
        self::assertFalse($this->eligible(), 'an unavailable person');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Command\IssueAcknowledgement\IssueAcknowledgementCommand;
use App\Giving\Application\Command\IssueAcknowledgement\IssueAcknowledgementHandler;
use App\Giving\Application\Contract\GiftDetail;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Application\Query\GetAcknowledgement\GetAcknowledgementHandler;
use App\Giving\Application\Query\GetAcknowledgement\GetAcknowledgementQuery;
use App\Giving\Application\Query\GetGiftAcknowledgement\GetGiftAcknowledgementHandler;
use App\Giving\Application\Query\GetGiftAcknowledgement\GetGiftAcknowledgementQuery;
use App\Giving\Domain\AcknowledgementLine;
use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Domain\Reversal;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Acknowledgement handlers in isolation: no database, no transaction, no identity context. */
final class AcknowledgementHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string GIFT = '00000000-0000-4000-8000-0000000000aa';
    private const string REQUEST = '00000000-0000-4000-8000-0000000000c1';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string OTHER = '00000000-0000-4000-8000-000000000002';

    private LockLog $log;
    private InMemoryGifts $gifts;
    private InMemoryAcknowledgements $acknowledgements;
    private FakeGivingReader $reader;
    private RecordingHistory $history;
    private RecordingPermissions $permissions;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->log = new LockLog();
        $this->gifts = new InMemoryGifts($this->log);
        $this->acknowledgements = new InMemoryAcknowledgements();
        $this->reader = new FakeGivingReader();
        $this->history = new RecordingHistory();
        $this->permissions = new RecordingPermissions(self::ACTOR, ['giving.acknowledge', 'giving.read']);
        // Still 1 June in UTC, but already 00:30 on 2 June in Johannesburg: the church's date is the 2nd.
        $this->clock = new MockClock('2026-06-01 22:30:00 UTC');
        $allocations = [new Allocation(self::OTHER, 3000), new Allocation(self::FUND, 7000)];
        $this->gifts->gifts[self::GIFT] = Gift::reconstitute(self::GIFT, null, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 5, 1), 'BANK', null, $allocations, self::ACTOR, new DateTimeImmutable('2026-05-01T08:00:00Z'));
        $this->reader->details[self::GIFT] = new GiftDetail(
            self::GIFT,
            null,
            Money::of(10000, 'ZAR'),
            CalendarDate::of(2026, 5, 1),
            'BANK',
            null,
            $allocations,
            self::ACTOR,
            new DateTimeImmutable('2026-05-01T08:00:00Z'),
            'Anonymous',
            [self::FUND => 'General', self::OTHER => 'Missions'],
            null,
            [],
            0,
        );
    }

    private function issue(string $actor = self::ACTOR, string $requestId = self::REQUEST, string $gift = self::GIFT): string
    {
        return (new IssueAcknowledgementHandler(
            new GivingAccess($this->permissions),
            $this->gifts,
            $this->acknowledgements,
            $this->reader,
            $this->history,
            new ChurchCalendar($this->clock, 'Africa/Johannesburg'),
            $this->clock,
        ))->handle(new IssueAcknowledgementCommand($actor, $requestId, $gift));
    }

    private function conflict(string $message, string $requestId = self::REQUEST): void
    {
        try {
            $this->issue(requestId: $requestId);
            self::fail('issued: '.$message);
        } catch (GivingException $error) {
            self::assertSame($message, $error->getMessage());
        }
    }

    public function testIssuingFreezesTheGiftsNamesOnTheChurchDateAndAuditsIt(): void
    {
        self::assertSame(self::REQUEST, $this->issue());

        $acknowledgement = $this->acknowledgements->acknowledgements[self::REQUEST];
        self::assertSame([self::GIFT, 'Anonymous', '2026-06-02'], [$acknowledgement->giftId, $acknowledgement->donorName, $acknowledgement->issuedOn->iso()]);
        self::assertEquals([new AcknowledgementLine(self::OTHER, 'Missions', 3000), new AcknowledgementLine(self::FUND, 'General', 7000)], $acknowledgement->lines);
        self::assertEquals(new DateTimeImmutable('2026-06-01 22:30:00 UTC'), $acknowledgement->createdAt);
        self::assertSame(GivingHash::of('acknowledgement', self::ACTOR, self::GIFT), $this->acknowledgements->hashes[self::REQUEST]);
        self::assertSame(['GIFT:ACKNOWLEDGEMENT_ISSUED:'.self::GIFT.':'], $this->history->entries);
        self::assertSame([self::ACTOR], $this->history->actors);
        self::assertSame(['gift:'.self::GIFT], $this->log->locks, 'the gift row is the only lock');
    }

    public function testAnIdenticalRetryReturnsTheSameAcknowledgementWithoutAuditingAgain(): void
    {
        $this->issue();
        self::assertSame(self::REQUEST, $this->issue());
        self::assertCount(1, $this->history->entries);
    }

    public function testARetryStillSucceedsAfterTheGiftIsReversed(): void
    {
        $this->issue();
        $this->gifts->reversals['r'] = Reversal::reconstitute('r', self::GIFT, CalendarDate::of(2026, 5, 2), 'Duplicate', self::ACTOR, new DateTimeImmutable());

        self::assertSame(self::REQUEST, $this->issue(), 'the replay check runs before the reversal check, as in Java');
    }

    public function testTheRequestIdCannotBeReusedForAnotherGift(): void
    {
        $this->issue();
        $other = '00000000-0000-4000-8000-0000000000ab';
        $this->gifts->gifts[$other] = $this->gifts->gifts[self::GIFT];

        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('This request identifier was already used.');
        $this->issue(gift: $other);
    }

    public function testOneAcknowledgementPerGift(): void
    {
        $this->issue();
        $this->conflict('This gift already has an acknowledgement. Refresh to open it.', '00000000-0000-4000-8000-0000000000c2');
        self::assertCount(1, $this->acknowledgements->acknowledgements);
        self::assertCount(1, $this->history->entries);
    }

    public function testReversedOrRefundedGiftsCannotBeAcknowledged(): void
    {
        $this->gifts->refunds[self::GIFT] = 1;
        $this->conflict('An acknowledgement cannot be issued for a reversed gift.');
        $this->gifts->refunds = [];
        $this->gifts->reversals['r'] = Reversal::reconstitute('r', self::GIFT, CalendarDate::of(2026, 5, 2), 'Duplicate', self::ACTOR, new DateTimeImmutable());
        $this->conflict('An acknowledgement cannot be issued for a reversed gift.');

        self::assertSame([], $this->acknowledgements->acknowledgements);
        self::assertSame([], $this->history->entries);
    }

    public function testAnUnknownGiftIsMissing(): void
    {
        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('The Giving record was not found.');
        $this->issue(gift: '00000000-0000-4000-8000-0000000000ff');
    }

    public function testThePermissionIsCheckedFirstAndASpoofedActorIsRefused(): void
    {
        foreach ([[self::ACTOR, ['giving.read', 'giving.manage']], ['00000000-0000-4000-8000-000000000999', ['giving.acknowledge']]] as [$actor, $granted]) {
            $this->permissions = new RecordingPermissions(self::ACTOR, $granted);
            try {
                $this->issue($actor);
                self::fail('issued without giving.acknowledge for the trusted caller');
            } catch (IdentityException) {
                self::assertSame([$actor.':giving.acknowledge'], $this->permissions->checks);
            }
        }
        self::assertSame([], $this->log->locks, 'nothing was locked before the permission check');
        self::assertSame([], $this->acknowledgements->acknowledgements);
        self::assertSame([], $this->history->entries);
    }

    public function testReadsNeedGivingReadAndNeverIssue(): void
    {
        $acknowledgements = new FakeAcknowledgementReader();
        $access = new GivingAccess($this->permissions);

        self::assertNull((new GetGiftAcknowledgementHandler($access, $acknowledgements))->handle(new GetGiftAcknowledgementQuery(self::GIFT)));
        try {
            (new GetAcknowledgementHandler($access, $acknowledgements))->handle(new GetAcknowledgementQuery(self::REQUEST));
            self::fail('an unknown acknowledgement was found');
        } catch (GivingException $error) {
            self::assertSame('The Giving record was not found.', $error->getMessage());
        }
        self::assertSame(['gift:'.self::GIFT, 'get:'.self::REQUEST], $acknowledgements->calls);
        self::assertSame([], $this->acknowledgements->acknowledgements);

        $this->permissions = new RecordingPermissions(self::ACTOR, ['giving.acknowledge']);
        $access = new GivingAccess($this->permissions);
        foreach ([
            static fn () => (new GetGiftAcknowledgementHandler($access, $acknowledgements))->handle(new GetGiftAcknowledgementQuery(self::GIFT)),
            static fn () => (new GetAcknowledgementHandler($access, $acknowledgements))->handle(new GetAcknowledgementQuery(self::REQUEST)),
        ] as $read) {
            try {
                $read();
                self::fail('read without giving.read');
            } catch (IdentityException) {
            }
        }
        self::assertSame(['giving.read', 'giving.read'], $this->permissions->checks);
        self::assertSame(['gift:'.self::GIFT, 'get:'.self::REQUEST], $acknowledgements->calls, 'the reader is not reached without permission');
    }
}

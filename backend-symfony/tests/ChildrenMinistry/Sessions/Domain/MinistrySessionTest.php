<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Domain;

use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MinistrySessionTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';

    private function session(SessionStatus $status = SessionStatus::Planned, int $version = 0): MinistrySession
    {
        return MinistrySession::reconstitute(self::ID, self::ID, self::ID, new DateTimeImmutable('2026-06-07T09:00:00Z'), new DateTimeImmutable('2026-06-07T11:00:00Z'), 20, $status, $version);
    }

    public function testAPlannedSessionStartsAtVersionZero(): void
    {
        $session = MinistrySession::plan(self::ID, self::ID, self::ID, new DateTimeImmutable('2026-06-07T09:00:00Z'), new DateTimeImmutable('2026-06-07T11:00:00Z'), 20);

        self::assertSame(SessionStatus::Planned, $session->status);
        self::assertSame(0, $session->version);
    }

    public function testTheEndMustFollowTheStartAndSeatsArePositive(): void
    {
        foreach ([['2026-06-07T09:00:00Z', '2026-06-07T09:00:00Z', 5, 'Validity end must follow its start.'], ['2026-06-07T09:00:00Z', '2026-06-07T08:00:00Z', 5, 'Validity end must follow its start.'], ['2026-06-07T09:00:00Z', '2026-06-07T10:00:00Z', 0, 'Invalid session details.']] as [$from, $until, $seats, $message]) {
            try {
                MinistrySession::plan(self::ID, self::ID, self::ID, new DateTimeImmutable($from), new DateTimeImmutable($until), $seats);
                self::fail('Expected a rejection.');
            } catch (ChildrenException $error) {
                self::assertSame($message, $error->getMessage());
            }
        }
    }

    /** @return iterable<string, array{SessionStatus, SessionStatus, ?string}> */
    public static function transitions(): iterable
    {
        $terminal = 'This session is terminal and cannot be reopened.';
        $planned = 'An operational session cannot return to planned.';
        yield 'planned to open' => [SessionStatus::Planned, SessionStatus::Open, null];
        yield 'planned to cancelled' => [SessionStatus::Planned, SessionStatus::Cancelled, null];
        yield 'planned to closed' => [SessionStatus::Planned, SessionStatus::Closed, null];
        yield 'open to needs review' => [SessionStatus::Open, SessionStatus::NeedsReview, null];
        yield 'needs review to open' => [SessionStatus::NeedsReview, SessionStatus::Open, null];
        yield 'open to closed' => [SessionStatus::Open, SessionStatus::Closed, null];
        yield 'open to planned' => [SessionStatus::Open, SessionStatus::Planned, $planned];
        yield 'needs review to planned' => [SessionStatus::NeedsReview, SessionStatus::Planned, $planned];
        yield 'closed to open' => [SessionStatus::Closed, SessionStatus::Open, $terminal];
        yield 'closed to planned' => [SessionStatus::Closed, SessionStatus::Planned, $terminal];
        yield 'cancelled to open' => [SessionStatus::Cancelled, SessionStatus::Open, $terminal];
        yield 'cancelled to closed' => [SessionStatus::Cancelled, SessionStatus::Closed, $terminal];
        yield 'closed to closed' => [SessionStatus::Closed, SessionStatus::Closed, null];
        yield 'planned to planned' => [SessionStatus::Planned, SessionStatus::Planned, null];
    }

    #[DataProvider('transitions')]
    public function testStatusTransitions(SessionStatus $from, SessionStatus $to, ?string $refusal): void
    {
        if (null !== $refusal) {
            $this->expectException(ChildrenException::class);
            $this->expectExceptionMessage($refusal);
        }

        $next = $this->session($from, 3)->withStatus($to, 3);

        self::assertSame($to, $next->status);
        self::assertSame(4, $next->version);
    }

    public function testAStaleVersionIsAConflictBeforeAnyStatusRule(): void
    {
        $this->expectException(ChildrenException::class);
        $this->expectExceptionMessage('This record changed. Reload before trying again.');

        $this->session(SessionStatus::Closed, 3)->withStatus(SessionStatus::Open, 2);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function arrivals(): iterable
    {
        yield 'thirty-one minutes before' => ['2026-06-07T08:29:00Z', false];
        yield 'exactly thirty minutes before' => ['2026-06-07T08:30:00Z', true];
        yield 'at the start' => ['2026-06-07T09:00:00Z', true];
        yield 'just before the end' => ['2026-06-07T10:59:59Z', true];
        yield 'at the end' => ['2026-06-07T11:00:00Z', false];
    }

    #[DataProvider('arrivals')]
    public function testArrivalsAreAllowedFromThirtyMinutesBeforeStartUntilTheEnd(string $now, bool $allowed): void
    {
        if (!$allowed) {
            $this->expectException(ChildrenException::class);
        }
        $this->session(SessionStatus::Open)->requireArrivalWindow(new DateTimeImmutable($now));

        self::assertTrue($allowed);
    }

    public function testArrivalsNeedAnOpenSession(): void
    {
        foreach ([SessionStatus::Planned, SessionStatus::NeedsReview, SessionStatus::Closed, SessionStatus::Cancelled] as $status) {
            try {
                $this->session($status)->requireArrivalWindow(new DateTimeImmutable('2026-06-07T09:30:00Z'));
                self::fail('Expected a rejection for '.$status->value);
            } catch (ChildrenException $error) {
                self::assertSame('Arrivals require an open session, from 30 minutes before its start until its end.', $error->getMessage());
            }
        }
    }
}

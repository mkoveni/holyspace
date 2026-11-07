<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Domain;

use App\ChildrenMinistry\Sessions\Domain\Attendance;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AttendanceTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';
    private const string OTHER = '22222222-2222-4222-8222-222222222222';

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    private function visit(): Attendance
    {
        return Attendance::arrive(self::ID, self::OTHER, self::ID, $this->at('2026-06-07T09:00:00Z'), self::OTHER, self::ID);
    }

    private function refused(string $message, callable $action): void
    {
        try {
            $action();
            self::fail('Expected a rejection.');
        } catch (ChildrenException $error) {
            self::assertSame($message, $error->getMessage());
        }
    }

    public function testArrivalStartsAnOpenVisitWithoutALabel(): void
    {
        $visit = $this->visit();

        self::assertSame([null, null, 0, 0, 0, false], [$visit->checkedOutAt, $visit->labelHash, $visit->labelVersion, $visit->failedAttempts, $visit->version, $visit->identityVerified]);
    }

    public function testTheFirstLabelNeedsNoReasonButAReplacementDoes(): void
    {
        $first = $this->visit()->issueLabel('hash1', $this->at('2026-06-08T09:00:00Z'), 0, null);

        self::assertSame(['hash1', 1, 1], [$first->labelHash, $first->labelVersion, $first->version]);
        $this->refused('A value is missing or exceeds its maximum length of 500.', fn () => $first->issueLabel('hash2', $this->at('2026-06-08T09:00:00Z'), 1, ' '));
        $second = $first->issueLabel('hash2', $this->at('2026-06-08T10:00:00Z'), 1, 'Lost it');
        self::assertSame(['hash2', 2, 2], [$second->labelHash, $second->labelVersion, $second->version]);
    }

    public function testIssuingChecksTheVersionAndThatTheVisitIsOpen(): void
    {
        $this->refused('This record changed. Reload before trying again.', fn () => $this->visit()->issueLabel('h', $this->at('2026-06-08T09:00:00Z'), 3, null));
        $collected = $this->visit()->collect(self::ID, self::OTHER, self::ID, self::ID, true, null, 0, $this->at('2026-06-07T10:00:00Z'));
        $this->refused('Child has already been collected.', fn () => $collected->issueLabel('h', $this->at('2026-06-08T09:00:00Z'), 1, null));
    }

    public function testCollectionEndsTheVisitAndClearsTheLabel(): void
    {
        $labelled = $this->visit()->issueLabel('hash1', $this->at('2026-06-08T09:00:00Z'), 0, null);

        $done = $labelled->collect(self::ID, self::OTHER, self::OTHER, self::ID, true, null, 1, $this->at('2026-06-07T10:00:00Z'));

        self::assertSame([null, 1, true, '', self::ID, self::OTHER, self::OTHER], [$done->labelHash, $done->labelVersion, $done->identityVerified, $done->exceptionReason, $done->collectorId, $done->checkedOutBy, $done->checkoutRequestId]);
        self::assertEquals($this->at('2026-06-07T10:00:00Z'), $done->checkedOutAt);
        self::assertSame(2, $done->version);
    }

    public function testCollectionNeedsVerifiedIdentityAHandoverAfterArrivalAndAnOpenVisit(): void
    {
        $this->refused('Confirm collector identity before handover.', fn () => $this->visit()->collect(self::ID, self::OTHER, self::OTHER, self::ID, false, null, 0, $this->at('2026-06-07T10:00:00Z')));
        $this->refused('Handover cannot predate arrival.', fn () => $this->visit()->collect(self::ID, self::OTHER, self::OTHER, self::ID, true, null, 0, $this->at('2026-06-07T08:59:59Z')));
        $this->refused('This record changed. Reload before trying again.', fn () => $this->visit()->collect(self::ID, self::OTHER, self::OTHER, self::ID, true, null, 2, $this->at('2026-06-07T10:00:00Z')));
    }

    public function testAnExceptionReasonIsKeptTrimmed(): void
    {
        $done = $this->visit()->collect(self::ID, self::OTHER, self::OTHER, self::ID, true, '  Phone lost  ', 0, $this->at('2026-06-07T10:00:00Z'));

        self::assertSame('Phone lost', $done->exceptionReason);
    }

    public function testFiveFailuresInOneWindowLockHandoverUntilTheWindowEnds(): void
    {
        $visit = $this->visit();
        $start = $this->at('2026-06-07T10:00:00Z');
        for ($i = 1; $i <= 4; ++$i) {
            $visit = $visit->failedAttempt($start->modify("+$i minutes"));
            self::assertFalse($visit->throttled($start->modify("+$i minutes")), "attempt $i");
        }

        $visit = $visit->failedAttempt($start->modify('+5 minutes'));

        self::assertSame(5, $visit->failedAttempts);
        self::assertTrue($visit->throttled($start->modify('+5 minutes')));
        self::assertEquals($start->modify('+1 minute +15 minutes'), $visit->attemptsResetAt, 'the window opened at the first failure');
        self::assertTrue($visit->throttled($start->modify('+15 minutes 59 seconds')));
        self::assertFalse($visit->throttled($start->modify('+16 minutes')), 'the window has ended');
    }

    public function testAFailureAfterTheWindowStartsANewWindow(): void
    {
        $visit = $this->visit()->failedAttempt($this->at('2026-06-07T10:00:00Z'))->failedAttempt($this->at('2026-06-07T10:01:00Z'));

        $later = $visit->failedAttempt($this->at('2026-06-07T10:15:00Z'));

        self::assertSame(1, $later->failedAttempts);
        self::assertEquals($this->at('2026-06-07T10:30:00Z'), $later->attemptsResetAt);
        self::assertSame(3, $later->version);
    }

    public function testTransferMovesTheVisitAndInvalidatesTheLabel(): void
    {
        $labelled = $this->visit()->issueLabel('hash1', $this->at('2026-06-08T09:00:00Z'), 0, null);

        $moved = $labelled->transfer(self::OTHER, 1);

        self::assertSame([self::OTHER, null, null, 2, 2], [$moved->sessionId, $moved->labelHash, $moved->labelExpiresAt, $moved->labelVersion, $moved->version]);
        $this->refused('Select a different target session.', fn () => $labelled->transfer(self::ID, 1));
        $this->refused('This record changed. Reload before trying again.', fn () => $labelled->transfer(self::OTHER, 0));
    }

    public function testDumpsNeverShowTheLabelHash(): void
    {
        $labelled = $this->visit()->issueLabel('secret-hash', $this->at('2026-06-08T09:00:00Z'), 0, null);

        self::assertStringNotContainsString('secret-hash', print_r($labelled, true));
        self::assertStringNotContainsString('secret-hash', var_export($labelled->__debugInfo(), true));
    }
}

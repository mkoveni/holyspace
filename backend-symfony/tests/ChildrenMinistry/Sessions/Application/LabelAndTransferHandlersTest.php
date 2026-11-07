<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Command\IssuePickupLabel\IssuePickupLabelCommand;
use App\ChildrenMinistry\Sessions\Application\Command\IssuePickupLabel\IssuePickupLabelHandler;
use App\ChildrenMinistry\Sessions\Application\Command\TransferChild\TransferChildCommand;
use App\ChildrenMinistry\Sessions\Application\Command\TransferChild\TransferChildHandler;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Sessions\Infrastructure\SecurePickupTokens;
use App\IdentityAccess\Domain\IdentityException;
use App\Tests\ChildrenMinistry\Participation\Application\RecordingChildrenAudit;
use App\Tests\People\Application\SequentialIds;

final class LabelAndTransferHandlersTest extends AttendanceScenario
{
    private const string VISIT = 'b0000000-0000-4000-8000-000000000001';
    private const string TARGET = 'e0000000-0000-4000-8000-000000000002';

    /** @param list<string> $permissions */
    private function labels(array $permissions = ['children.station']): IssuePickupLabelHandler
    {
        return new IssuePickupLabelHandler($this->permissions($permissions), new RecordingChildrenAudit($this->log), $this->clock, new SequentialIds(), $this->access(), $this->scope($permissions), $this->world, $this->world, $this->world, new SecurePickupTokens());
    }

    public function testIssuingStoresOnlyTheHashAndShowsTheCodeOnce(): void
    {
        $this->present();

        $label = $this->labels()->handle(new IssuePickupLabelCommand(self::STATION, self::VISIT, 0, null));

        self::assertSame(['read:attendance', 'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:session', 'forUpdate:attendance', 'update:attendance', 'audit:pickup.label.issued', 'insert:event:LABEL_ISSUED'], $this->log->entries);
        $stored = $this->world->attendances[self::VISIT];
        self::assertSame([1, 1, 1], [$label->labelVersion, $label->attendanceVersion, $stored->labelVersion]);
        self::assertNotSame($label->pickupCode, $stored->labelHash);
        self::assertSame(hash('sha256', $label->pickupCode), $stored->labelHash);
        self::assertSame(22, strlen($label->pickupCode), '128 random bits, url-safe base64');
        self::assertEquals($this->clock->now()->modify('+24 hours'), $label->expiresAt);
        self::assertStringNotContainsString($label->pickupCode, print_r($label, true));
    }

    public function testReplacingNeedsAReasonAndInvalidatesTheOldCode(): void
    {
        $this->present();
        $first = $this->labels()->handle(new IssuePickupLabelCommand(self::STATION, self::VISIT, 0, null));

        $this->refused('A value is missing or exceeds its maximum length of 500.', fn () => $this->labels()->handle(new IssuePickupLabelCommand(self::STATION, self::VISIT, 1, null)));
        $second = $this->labels()->handle(new IssuePickupLabelCommand(self::STATION, self::VISIT, 1, 'Lost the first'));

        self::assertNotSame($first->pickupCode, $second->pickupCode);
        $tokens = new SecurePickupTokens();
        self::assertFalse($tokens->matches($first->pickupCode, $this->world->attendances[self::VISIT]->labelHash));
        self::assertTrue($tokens->matches($second->pickupCode, $this->world->attendances[self::VISIT]->labelHash));
        self::assertSame('Lost the first', $this->world->events[1]->reason);
    }

    public function testLabelsNeedStationPermissionAssignmentAnOpenVisitAndTheRightVersion(): void
    {
        $this->present();
        try {
            $this->labels(['children.read'])->handle(new IssuePickupLabelCommand(self::STATION, self::VISIT, 0, null));
            self::fail('Expected a refusal.');
        } catch (IdentityException) {
        }
        self::assertSame([], $this->log->entries);
        $this->refused('This operation is outside your assigned session.', fn () => $this->labels()->handle(new IssuePickupLabelCommand('stranger', self::VISIT, 0, null)));
        $this->refused('This record changed. Reload before trying again.', fn () => $this->labels()->handle(new IssuePickupLabelCommand(self::STATION, self::VISIT, 9, null)));
        $this->refused('Attendance was not found.', fn () => $this->labels()->handle(new IssuePickupLabelCommand(self::STATION, 'b0000000-0000-4000-8000-0000000000ff', 0, null)));
    }

    /** A second open session in the same room family, with its own present leader. */
    private function targetSession(SessionStatus $status = SessionStatus::Open): void
    {
        $this->world->sessions[self::TARGET] = MinistrySession::reconstitute(self::TARGET, SessionFixture::CLASS_ID, SessionFixture::ROOM, $this->at('2026-06-07T09:00:00Z'), $this->at('2026-06-07T11:00:00Z'), 10, $status, 0);
        $this->world->staff[] = new \App\ChildrenMinistry\Sessions\Domain\SessionStaff(self::TARGET, self::LEADER, \App\ChildrenMinistry\Sessions\Domain\StaffRole::Leader, true);
    }

    /** @param list<string> $permissions */
    private function transfer(array $permissions = ['children.supervise']): TransferChildHandler
    {
        return new TransferChildHandler($this->permissions($permissions), new RecordingChildrenAudit($this->log), $this->clock, new SequentialIds(), $this->access(), $this->world, $this->world, $this->world, $this->world->operations(), $this->admission());
    }

    public function testTransferLocksTheChildThenBothSessionsInIdOrderThenTheVisit(): void
    {
        $this->targetSession();
        $this->present(null);

        $view = $this->transfer()->handle(new TransferChildCommand(self::STATION, self::VISIT, 0, self::TARGET, 'Room full'));

        self::assertSame([
            'read:attendance', 'person:'.self::CHILD, 'guard:'.self::CHILD, 'forUpdate:session', 'forUpdate:session', 'forUpdate:attendance', 'forUpdate:session',
            'read:occupancy', 'read:enrolmentAt', 'read:assignmentAt', 'close:segment', 'update:attendance', 'insert:segment', 'insert:event:TRANSFERRED', 'audit:child.transferred',
        ], $this->log->entries);
        self::assertSame([self::TARGET, 1, false], [$view->sessionId, $view->version, $view->labelIssued]);
        self::assertSame(self::TARGET, $this->world->segments[0]->sessionId);
    }

    public function testTransferNeedsSupervisionAReasonAnOpenTargetWithSpaceAndAdmission(): void
    {
        $this->targetSession();
        $this->present();
        try {
            $this->transfer(['children.station'])->handle(new TransferChildCommand(self::STATION, self::VISIT, 0, self::TARGET, 'x'));
            self::fail('Expected a refusal.');
        } catch (IdentityException) {
        }
        self::assertSame([], $this->log->entries);

        $this->refused('A value is missing or exceeds its maximum length of 500.', fn () => $this->transfer()->handle(new TransferChildCommand(self::STATION, self::VISIT, 0, self::TARGET, ' ')));
        $this->refused('Select a different target session.', fn () => $this->transfer()->handle(new TransferChildCommand(self::STATION, self::VISIT, 0, SessionFixture::SESSION, 'x')));
        $this->refused('Session was not found.', fn () => $this->transfer()->handle(new TransferChildCommand(self::STATION, self::VISIT, 0, 'e0000000-0000-4000-8000-0000000000ff', 'x')));

        $this->world->sessions[self::TARGET] = $this->world->sessions[self::TARGET]->withStatus(SessionStatus::NeedsReview, 0);
        $this->refused('Arrivals require an open session, from 30 minutes before its start until its end.', fn () => $this->transfer()->handle(new TransferChildCommand(self::STATION, self::VISIT, 0, self::TARGET, 'x')));

        $this->targetSession();
        unset($this->world->enrolments[SessionFixture::PROGRAMME.self::CHILD]);
        $this->refused('Active programme enrolment is required for the session date.', fn () => $this->transfer()->handle(new TransferChildCommand(self::STATION, self::VISIT, 0, self::TARGET, 'x')));

        self::assertSame(SessionFixture::SESSION, $this->world->attendances[self::VISIT]->sessionId, 'every refusal leaves the child where they were');
        self::assertSame([], $this->world->segments);
    }

    public function testTransferRefusesAStaleVersionAndACollectedChild(): void
    {
        $this->targetSession();
        $this->present();
        $this->refused('This record changed. Reload before trying again.', fn () => $this->transfer()->handle(new TransferChildCommand(self::STATION, self::VISIT, 4, self::TARGET, 'x')));

        $visit = $this->world->attendances[self::VISIT]->collect(self::COLLECTOR, self::STATION, self::REQUEST, self::GRANT, true, null, 0, $this->at('2026-06-07T08:50:00Z'));
        $this->world->attendances[self::VISIT] = $visit;
        $this->refused('Child has already been collected.', fn () => $this->transfer()->handle(new TransferChildCommand(self::STATION, self::VISIT, 1, self::TARGET, 'x')));
    }
}

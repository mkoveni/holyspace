<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Presentation;

use DateTimeImmutable;

/** Port of Java `VolunteeringApiIntegrationTest` (roster, readiness and invitations), plus the rules Java tests leave implicit. */
final class VolunteerRosterApiTest extends VolunteeringApiTestCase
{
    public function testConfirmationAndReadinessAreIndependentAndCredentialRevocationRemainsLive(): void
    {
        $this->book($this->volunteer, 'CONFIRMED');
        $before = $this->shiftDetails($this->shift);
        self::assertSame(1, $before['confirmed']);
        self::assertSame(0, $before['ready']);
        $this->ready();
        self::assertSame(1, $this->shiftDetails($this->shift)['ready']);
        $credential = $this->profile()['credentials'][0];
        $this->serving('POST', "/volunteers/{$this->volunteer}/credentials/revoke", ['expectedVersion' => $this->volunteerVersion(), 'credentialId' => $credential['id'], 'reason' => 'Evidence withdrawn']);
        self::assertSame(204, $this->code(), $this->body());
        $after = $this->shiftDetails($this->shift);
        self::assertSame(1, $after['confirmed']);
        self::assertSame(0, $after['ready']);
        self::assertStringNotContainsString('Verified source', $this->body());
    }

    public function testMatchingRequestRetriesAreIdempotentAndChangedRequestsAndStaleVersionsAreRejected(): void
    {
        $key = self::uuid();
        $payload = $this->draft($key, $this->start->modify('+7200 seconds'), $this->end->modify('+7200 seconds'), 1);
        $first = $this->add('/shifts', $payload);
        self::assertSame($first, $this->add('/shifts', $payload));
        $payload['positions'] = 2;
        $this->serving('POST', '/shifts', $payload);
        self::assertSame(409, $this->code());
        self::assertSame('This request identifier was already used for another shift.', $this->message());
        $this->book($this->volunteer, 'PROPOSED');
        $this->serving('PUT', "/shifts/{$this->shift}", ['expectedVersion' => 0, 'startsAt' => self::at($this->start), 'endsAt' => self::at($this->end), 'positions' => 2, 'status' => 'OPEN']);
        self::assertSame(409, $this->code());
    }

    public function testOverlappingAssignmentsAreRejectedButAdjacentDutiesAreAllowed(): void
    {
        $this->book($this->volunteer, 'PROPOSED');
        $other = $this->add('/shifts', $this->draft(self::uuid(), $this->start->modify('+60 seconds'), $this->end->modify('+60 seconds'), 1));
        $this->serving('POST', '/assignments', ['requestId' => self::uuid(), 'shiftId' => $other, 'expectedVersion' => 0, 'volunteerId' => $this->volunteer, 'status' => 'CONFIRMED']);
        self::assertSame(409, $this->code());
        self::assertSame('The volunteer has an overlapping assignment.', $this->message());
        $adjacent = $this->add('/shifts', $this->draft(self::uuid(), $this->end, $this->end->modify('+3600 seconds'), 1));
        $this->serving('POST', '/assignments', ['requestId' => self::uuid(), 'shiftId' => $adjacent, 'expectedVersion' => 0, 'volunteerId' => $this->volunteer, 'status' => 'PROPOSED']);
        self::assertSame(201, $this->code());
    }

    public function testAnotherBookingDoesNotInvalidateAnInvitationButAChangedDutyDoes(): void
    {
        $assignment = $this->book($this->volunteer, 'PROPOSED');
        $invitation = $this->invite($assignment);
        $another = $this->add('/volunteers', ['personId' => $this->person('Naledi')]);
        $this->book($another, 'PROPOSED');
        $this->serving('POST', "/invitations/$invitation/respond", ['expectedVersion' => 0, 'decision' => 'ACCEPTED']);
        self::assertSame(204, $this->code(), $this->body());
        $fresh = $this->invite($assignment, 1);
        $this->serving('PUT', "/shifts/{$this->shift}", ['expectedVersion' => $this->shiftVersion($this->shift), 'startsAt' => self::at($this->start->modify('+60 seconds')), 'endsAt' => self::at($this->end->modify('+60 seconds')), 'positions' => 2, 'status' => 'OPEN']);
        self::assertSame(204, $this->code(), $this->body());
        $this->serving('POST', "/invitations/$fresh/respond", ['expectedVersion' => 0, 'decision' => 'ACCEPTED']);
        self::assertSame(409, $this->code());
    }

    public function testInvitationsBelongToTheLinkedAccountAndDecliningReleasesCapacity(): void
    {
        $assignment = $this->book($this->volunteer, 'PROPOSED');
        $invitation = $this->invite($assignment);
        $this->accountWith('server', ['volunteering.serve']);
        $this->serving('POST', "/invitations/$invitation/respond", ['expectedVersion' => 0, 'decision' => 'ACCEPTED'], $this->readerToken());
        self::assertSame(403, $this->code());
        $this->serving('POST', "/invitations/$invitation/respond", ['expectedVersion' => 0, 'decision' => 'ACCEPTED'], $this->login('server'));
        self::assertSame(403, $this->code());
        self::assertSame('You cannot perform this volunteer action.', $this->message());
        $this->serving('POST', "/invitations/$invitation/respond", ['expectedVersion' => 0, 'decision' => 'DECLINED']);
        self::assertSame(204, $this->code(), $this->body());
        self::assertSame(0, $this->shiftDetails($this->shift)['reserved']);
        $this->serving('POST', "/invitations/$invitation/respond", ['expectedVersion' => 0, 'decision' => 'ACCEPTED']);
        self::assertSame(409, $this->code());
    }

    public function testAtomicBulkRollsBackEveryShiftWhenOneDraftFails(): void
    {
        $good = self::uuid();
        $this->serving('POST', '/shifts/bulk', [$this->draft($good, $this->end, $this->end->modify('+60 seconds'), 1), $this->draft(self::uuid(), $this->end, $this->start, 1)]);
        self::assertSame(400, $this->code());
        $this->serving('GET', '/shifts?from='.self::at($this->start).'&until='.self::at($this->end->modify('+86400 seconds')));
        self::assertSame(1, $this->json()['total']);

        $drafts = [$this->draft($good, $this->end, $this->end->modify('+60 seconds'), 1), $this->draft(self::uuid(), $this->end->modify('+60 seconds'), $this->end->modify('+120 seconds'), 1)];
        $this->serving('POST', '/shifts/bulk', $drafts);
        self::assertSame(200, $this->code(), $this->body());
        $ids = $this->json();
        self::assertCount(2, $ids);
        $this->serving('POST', '/shifts/bulk', $drafts);
        self::assertSame($ids, $this->json(), 'a retried bulk request returns the same shifts');
        foreach ([[], [null], array_fill(0, 26, $drafts[0])] as $invalid) {
            $this->serving('POST', '/shifts/bulk', $invalid);
            self::assertSame(400, $this->code());
        }
    }

    public function testFullDutyCredentialCoverageIsRequiredForReadinessButNeverBlocksPlanning(): void
    {
        $this->add("/volunteers/{$this->volunteer}/credentials", ['expectedVersion' => 0, 'typeId' => $this->type, 'validFrom' => self::at($this->validFrom), 'validUntil' => self::at($this->start->modify('+30 seconds')), 'evidence' => 'Ends during duty']);
        $this->book($this->volunteer, 'CONFIRMED');
        $result = $this->shiftDetails($this->shift);
        self::assertSame(0, $result['ready']);
        self::assertStringContainsString('Screening must be verified', $this->body());
    }

    public function testProtectedEndpointsRejectAnonymousAndReadOnlyCoordinators(): void
    {
        $reader = $this->readerToken();
        $this->call('GET', '/api/volunteering/shifts?from='.self::at($this->start).'&until='.self::at($this->end));
        self::assertSame(401, $this->code());
        $this->serving('POST', '/shifts', $this->draft(self::uuid(), $this->start, $this->end, 1), $reader);
        self::assertSame(403, $this->code());
        $this->serving('POST', '/assignments', ['requestId' => self::uuid(), 'shiftId' => $this->shift, 'expectedVersion' => 1, 'volunteerId' => $this->volunteer, 'status' => 'PROPOSED'], $reader);
        self::assertSame(403, $this->code());
        $this->serving('POST', '/invitations/expire', ['limit' => 10], $reader);
        self::assertSame(403, $this->code());
        $this->serving('GET', "/shifts/{$this->shift}", null, $reader);
        self::assertSame(200, $this->code());
        self::assertSame(0, $this->rows('serving_assignments'));
    }

    public function testExpiringAnInvitationReleasesOnlyProposedHoldsAndQueriesNeverMaintainState(): void
    {
        $assignment = $this->book($this->volunteer, 'CONFIRMED');
        $invitation = $this->invite($assignment);
        $this->connection->executeStatement("UPDATE serving_invitations SET expires_at = now() - interval '1 second' WHERE id = ?", [$invitation]);
        $this->serving('GET', '/me/invitations');
        self::assertSame(200, $this->code(), $this->body());
        self::assertSame('EXPIRED', $this->json()[0]['effectiveStatus']);
        self::assertSame('PENDING', $this->connection->fetchOne('SELECT status FROM serving_invitations WHERE id = ?', [$invitation]));
        $this->serving('POST', '/invitations/expire', ['limit' => 100]);
        self::assertSame(200, $this->code(), $this->body());
        self::assertSame('1', $this->body());
        self::assertSame(1, $this->shiftDetails($this->shift)['confirmed']);
        self::assertSame('EXPIRED', $this->connection->fetchOne('SELECT status FROM serving_invitations WHERE id = ?', [$invitation]));
    }

    public function testExpiryReleasesAProposedHoldTheInvitationStillDescribes(): void
    {
        $assignment = $this->book($this->volunteer, 'PROPOSED');
        $invitation = $this->invite($assignment);
        $this->connection->executeStatement("UPDATE serving_invitations SET expires_at = now() - interval '1 second' WHERE id = ?", [$invitation]);
        $this->serving('POST', '/invitations/expire', ['limit' => 100]);
        self::assertSame('1', $this->body());
        self::assertSame('CANCELLED', $this->connection->fetchOne('SELECT status FROM serving_assignments WHERE id = ?', [$assignment]));
        self::assertSame(0, $this->shiftDetails($this->shift)['reserved']);
        $this->serving('POST', '/invitations/expire', ['limit' => 100]);
        self::assertSame('0', $this->body());
        foreach ([0, 101] as $limit) {
            $this->serving('POST', '/invitations/expire', ['limit' => $limit]);
            self::assertSame(400, $this->code());
        }
        $this->serving('GET', "/assignments/$assignment/events");
        self::assertSame(['INVITATION_EXPIRED', 'INVITED', 'ASSIGNED'], array_column($this->json(), 'action'));
    }

    public function testRevisedDutyTimesRequireRenewedCommitment(): void
    {
        $this->book($this->volunteer, 'CONFIRMED');
        $this->serving('PUT', "/shifts/{$this->shift}", ['expectedVersion' => $this->shiftVersion($this->shift), 'startsAt' => self::at($this->start->modify('+60 seconds')), 'endsAt' => self::at($this->end->modify('+60 seconds')), 'positions' => 2, 'status' => 'OPEN']);
        self::assertSame(204, $this->code(), $this->body());
        $revised = $this->shiftDetails($this->shift);
        self::assertSame(0, $revised['confirmed']);
        self::assertSame('PROPOSED', $revised['assignments'][0]['assignment']['status']);
        self::assertSame(1, $revised['shift']['scheduleRevision']);
    }

    public function testCancellingAShiftCancelsItsBookingsAndCannotReopen(): void
    {
        $assignment = $this->book($this->volunteer, 'PROPOSED');
        $invitation = $this->invite($assignment);
        $this->serving('PUT', "/shifts/{$this->shift}", ['expectedVersion' => $this->shiftVersion($this->shift), 'startsAt' => self::at($this->start), 'endsAt' => self::at($this->end), 'positions' => 1, 'status' => 'OPEN']);
        self::assertSame(204, $this->code(), $this->body());
        $this->serving('PUT', "/shifts/{$this->shift}", ['expectedVersion' => $this->shiftVersion($this->shift), 'startsAt' => self::at($this->start), 'endsAt' => self::at($this->end), 'positions' => 0, 'status' => 'OPEN']);
        self::assertSame(409, $this->code());
        self::assertSame('Positions cannot be reduced below reserved assignments.', $this->message());
        $this->serving('PUT', "/shifts/{$this->shift}", ['expectedVersion' => $this->shiftVersion($this->shift), 'startsAt' => self::at($this->start), 'endsAt' => self::at($this->end), 'positions' => 1, 'status' => 'CANCELLED']);
        self::assertSame(204, $this->code(), $this->body());
        self::assertSame('CANCELLED', $this->connection->fetchOne('SELECT status FROM serving_assignments WHERE id = ?', [$assignment]));
        self::assertSame('WITHDRAWN', $this->connection->fetchOne('SELECT status FROM serving_invitations WHERE id = ?', [$invitation]));
        $this->serving('PUT', "/shifts/{$this->shift}", ['expectedVersion' => $this->shiftVersion($this->shift), 'startsAt' => self::at($this->start), 'endsAt' => self::at($this->end), 'positions' => 1, 'status' => 'OPEN']);
        self::assertSame(409, $this->code());
        self::assertSame('Cancelled shifts cannot reopen.', $this->message());
        $this->serving('POST', '/assignments', ['requestId' => self::uuid(), 'shiftId' => $this->shift, 'expectedVersion' => $this->shiftVersion($this->shift), 'volunteerId' => $this->volunteer, 'status' => 'PROPOSED']);
        self::assertSame(409, $this->code());
        self::assertSame('Choose an upcoming open shift.', $this->message());
    }

    public function testAssignmentStatusChangesAreVersionedAndRecorded(): void
    {
        $assignment = $this->book($this->volunteer, 'PROPOSED');
        $this->serving('POST', "/assignments/$assignment/status", ['expectedVersion' => 0, 'status' => 'CONFIRMED', 'reason' => 'Phoned']);
        self::assertSame(204, $this->code(), $this->body());
        $this->serving('POST', "/assignments/$assignment/status", ['expectedVersion' => 0, 'status' => 'CANCELLED', 'reason' => 'Stale']);
        self::assertSame(409, $this->code());
        $this->serving('POST', "/assignments/$assignment/status", ['expectedVersion' => 1, 'status' => 'MAYBE', 'reason' => 'Unsure']);
        self::assertSame(400, $this->code());
        $this->serving('POST', "/assignments/$assignment/status", ['expectedVersion' => 1, 'status' => 'CANCELLED', 'reason' => 'Unable']);
        self::assertSame(204, $this->code());
        $this->serving('POST', "/assignments/$assignment/status", ['expectedVersion' => 2, 'status' => 'CONFIRMED', 'reason' => 'Back']);
        self::assertSame(409, $this->code());
        self::assertSame('A cancelled assignment cannot reopen.', $this->message());
        $this->serving('GET', "/assignments/$assignment/events?limit=2");
        self::assertSame(['STATUS_CHANGED', 'STATUS_CHANGED'], array_column($this->json(), 'action'));
        self::assertSame('Unable', $this->json()[0]['reason']);
        self::assertSame(2, $this->rows('serving_audit', "action = 'assignment.changed'"));
        $this->serving('GET', "/assignments/$assignment/events?limit=0");
        self::assertSame(400, $this->code());
    }

    public function testFullShiftsAndInactiveVolunteersCannotBeBooked(): void
    {
        $this->book($this->volunteer, 'PROPOSED');
        $this->book($this->add('/volunteers', ['personId' => $this->person('Naledi')]), 'PROPOSED');
        $third = $this->add('/volunteers', ['personId' => $this->person('Thabo')]);
        $this->serving('POST', '/assignments', ['requestId' => self::uuid(), 'shiftId' => $this->shift, 'expectedVersion' => $this->shiftVersion($this->shift), 'volunteerId' => $third, 'status' => 'PROPOSED']);
        self::assertSame(409, $this->code());
        self::assertSame('All positions are reserved.', $this->message());
        $this->serving('PUT', "/volunteers/$third/status", ['expectedVersion' => 0, 'status' => 'PAUSED']);
        self::assertSame(204, $this->code(), $this->body());
        $this->serving('POST', '/assignments', ['requestId' => self::uuid(), 'shiftId' => $this->shift, 'expectedVersion' => $this->shiftVersion($this->shift), 'volunteerId' => $third, 'status' => 'PROPOSED']);
        self::assertSame(409, $this->code());
        self::assertSame('Only active volunteers can be scheduled.', $this->message());
        $this->serving('POST', '/assignments', ['requestId' => self::uuid(), 'shiftId' => $this->shift, 'expectedVersion' => 0, 'volunteerId' => $third, 'status' => 'CANCELLED']);
        self::assertSame(400, $this->code());
    }

    public function testShiftRulesAndJsonShape(): void
    {
        $this->serving('POST', '/shifts', $this->draft(self::uuid(), $this->start, $this->start->modify('+8 days'), 1));
        self::assertSame(400, $this->code());
        self::assertSame('Choose a source, at most seven days and 1–100 positions.', $this->message());
        $this->serving('POST', '/shifts', ['requestId' => self::uuid(), 'teamId' => $this->team, 'roleId' => $this->role, 'sourceKind' => 'STANDALONE', 'sourceId' => self::uuid(), 'startsAt' => self::at($this->start), 'endsAt' => self::at($this->end), 'positions' => 1]);
        self::assertSame(400, $this->code());
        self::assertSame('Choose a valid activity source.', $this->message());
        $this->serving('POST', '/shifts', [...$this->draft(self::uuid(), $this->start, $this->end, 1), 'roleId' => self::uuid()]);
        self::assertSame(404, $this->code());
        self::assertSame('Active team role was not found.', $this->message());
        $this->serving('POST', '/shifts', [...$this->draft(self::uuid(), $this->start, $this->end, 1), 'startsAt' => 'tomorrow']);
        self::assertSame(400, $this->code());
        $this->serving('POST', '/shifts', [...$this->draft(self::uuid(), $this->start, $this->end, 1), 'requestId' => null]);
        self::assertSame(400, $this->code());
        self::assertSame('An identifier is required.', $this->message());

        $details = $this->shiftDetails($this->shift);
        self::assertSame(['shift', 'team', 'role', 'assignments', 'reserved', 'confirmed', 'ready', 'vacancies'], array_keys($details));
        self::assertSame(['id', 'requestId', 'requestHash', 'teamId', 'roleId', 'sourceKind', 'sourceId', 'sourceRevision', 'startsAt', 'endsAt', 'positions', 'status', 'version', 'scheduleRevision'], array_keys($details['shift']));
        self::assertSame(['Welcome team', 'Welcomer', 2], [$details['team'], $details['role'], $details['vacancies']]);
        self::assertSame(self::at($this->start), $details['shift']['startsAt']);
        self::assertSame(1, $this->rows('serving_audit', "action = 'shift.created'"));

        $this->serving('GET', '/shifts?from='.self::at($this->start).'&until='.self::at($this->start->modify('+91 days')));
        self::assertSame(400, $this->code());
        self::assertSame('Choose at most 90 days.', $this->message());
        $this->serving('GET', '/shifts?until='.self::at($this->start));
        self::assertSame(400, $this->code());
        $this->serving('GET', '/shifts?from='.self::at($this->start).'&until='.self::at($this->end).'&teamId='.self::uuid());
        self::assertSame(['shifts' => [], 'total' => 0], $this->json());
        $this->serving('GET', '/shifts/'.self::uuid());
        self::assertSame(404, $this->code());
        self::assertSame('Shift was not found.', $this->message());
    }

    public function testReadinessQueryAndActivities(): void
    {
        $this->serving('GET', "/readiness?volunteerId={$this->volunteer}&shiftId={$this->shift}");
        self::assertSame(200, $this->code(), $this->body());
        self::assertFalse($this->json()['ready']);
        self::assertContains('Team membership must cover the entire shift.', $this->json()['issues']);
        $this->ready();
        $this->serving('GET', "/readiness?volunteerId={$this->volunteer}&shiftId={$this->shift}");
        self::assertSame(['ready' => true, 'issues' => []], $this->json());
        $this->serving('GET', "/readiness?volunteerId={$this->volunteer}");
        self::assertSame(400, $this->code());

        $this->serving('GET', '/activities?kind=UNKNOWN&from='.self::at($this->start).'&until='.self::at($this->end));
        self::assertSame([], $this->json());
        $this->serving('GET', '/activities?kind=EVENT_OCCURRENCE&from='.self::at($this->end).'&until='.self::at($this->start));
        self::assertSame(400, $this->code());
    }

    public function testInvitationExpiryMustFallBeforeTheShift(): void
    {
        $assignment = $this->book($this->volunteer, 'PROPOSED');
        foreach ([$this->start->modify('+1 second'), new DateTimeImmutable('-1 minute')] as $expiry) {
            $this->serving('POST', "/assignments/$assignment/invitations", ['expectedVersion' => 0, 'expiresAt' => self::at($expiry)]);
            self::assertSame(400, $this->code());
            self::assertSame('Invitation expiry must be in the future and no later than shift start.', $this->message());
        }
        $first = $this->invite($assignment);
        $second = $this->invite($assignment);
        self::assertSame('WITHDRAWN', $this->connection->fetchOne('SELECT status FROM serving_invitations WHERE id = ?', [$first]));
        $this->serving('GET', "/shifts/{$this->shift}");
        self::assertSame([$second, $first], array_column(array_column($this->json()['assignments'][0]['invitations'], 'invitation'), 'id'));
        $this->serving('POST', "/invitations/$second/respond", ['expectedVersion' => 0, 'decision' => 'MAYBE']);
        self::assertSame(400, $this->code());
        self::assertSame('Choose accepted or declined.', $this->message());
    }
}

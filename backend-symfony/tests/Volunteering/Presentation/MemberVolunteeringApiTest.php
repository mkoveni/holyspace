<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Presentation;

/** Port of Java `MemberVolunteeringApiIntegrationTest`: own duties, answers, availability and duty notices in the inbox. */
final class MemberVolunteeringApiTest extends VolunteeringApiTestCase
{
    private const string ROOT = '/api/member/volunteering';

    /** @param array<string, mixed>|null $body */
    private function member(string $method, string $path = '', ?array $body = null, ?string $token = null): void
    {
        $this->call($method, self::ROOT.$path, $token ?? $this->admin, $body);
    }

    /** @return array<string|int, mixed> */
    private function inbox(?string $token = null): array
    {
        $this->call('GET', '/api/member/notifications', $token ?? $this->admin);
        self::assertSame(200, $this->code(), $this->body());

        return $this->json();
    }

    /**
     * @param list<array<string, mixed>> $exceptions
     *
     * @return array<string, mixed>
     */
    private function availability(string $volunteer, int $version, array $exceptions = []): array
    {
        return ['volunteerId' => $volunteer, 'expectedVersion' => $version, 'weekly' => [], 'exceptions' => $exceptions];
    }

    public function testSourceCommandsCreatePrivateDeduplicatedInboxNoticesAndHonorOptOut(): void
    {
        $draft = ['requestId' => self::uuid(), 'shiftId' => $this->shift, 'expectedVersion' => 0, 'volunteerId' => $this->volunteer, 'status' => 'PROPOSED'];
        $assignment = $this->add('/assignments', $draft);
        self::assertSame($assignment, $this->add('/assignments', $draft));
        self::assertSame(1, $this->inbox()['unreadCount']);
        self::assertStringContainsString('A duty has been added', $this->body());
        foreach (['Solly', 'accountId', 'sourceKey'] as $private) {
            self::assertStringNotContainsString($private, $this->body());
        }
        $this->invite($assignment);
        $this->inbox();
        self::assertStringContainsString('invited to serve', $this->body());
        $this->serving('PUT', "/shifts/{$this->shift}", ['expectedVersion' => $this->shiftVersion($this->shift), 'startsAt' => self::at($this->start), 'endsAt' => self::at($this->end), 'positions' => 2, 'status' => 'CANCELLED']);
        self::assertSame(204, $this->code(), $this->body());
        self::assertSame(3, $this->inbox()['unreadCount']);
        self::assertStringContainsString('serving plans have changed', $this->body());
        self::assertSame(0, $this->inbox($this->readerToken())['unreadCount']);
        $this->call('PUT', '/api/member/notification-preferences/VOLUNTEERING', $this->admin, ['enabled' => false, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code(), $this->body());
        $next = $this->add('/shifts', $this->draft(self::uuid(), $this->end, $this->end->modify('+3600 seconds'), 1));
        $this->add('/assignments', ['requestId' => self::uuid(), 'shiftId' => $next, 'expectedVersion' => 0, 'volunteerId' => $this->volunteer, 'status' => 'PROPOSED']);
        self::assertSame(3, $this->inbox()['unreadCount']);
    }

    public function testOwnOverviewIncludesAssignmentsWithoutInvitationsAndExcludesPrivateEvidence(): void
    {
        $this->ready();
        $assignment = $this->book($this->volunteer, 'CONFIRMED');
        $other = $this->add('/volunteers', ['personId' => $this->person('Other')]);
        $hidden = $this->book($other, 'PROPOSED');
        $this->member('GET');
        self::assertSame(200, $this->code(), $this->body());
        self::assertTrue($this->json()['linked']);
        foreach ([$assignment, 'Welcome team', 'Welcomer', '"ready":true'] as $expected) {
            self::assertStringContainsString($expected, $this->body());
        }
        foreach ([$hidden, 'accountId', 'personId', 'Verified source', 'requestHash', 'createdBy'] as $private) {
            self::assertStringNotContainsString($private, $this->body());
        }
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame(['permitted', 'linked', 'volunteerId', 'status', 'version', 'weekly', 'exceptions', 'items', 'hasMore'], array_keys($this->json()));
        self::assertSame(['id', 'status', 'shiftStatus', 'team', 'role', 'activity', 'location', 'startsAt', 'endsAt', 'readiness', 'invitation'], array_keys($this->json()['items'][0]));
        self::assertNull($this->json()['items'][0]['invitation']);
        $this->member('GET', '?limit=0');
        self::assertSame(400, $this->code());
        $this->call('GET', self::ROOT);
        self::assertSame(401, $this->code());
    }

    public function testScopeNeedsBothLiveServingPermissionAndExplicitVolunteerLink(): void
    {
        $assignment = $this->book($this->volunteer, 'PROPOSED');
        $this->member('GET', '', null, $this->readerToken());
        self::assertStringContainsString('"permitted":false', $this->body());
        self::assertStringNotContainsString($assignment, $this->body());
        $this->connection->executeStatement('UPDATE serving_volunteers SET account_id = NULL WHERE id = ?', [$this->volunteer]);
        $this->member('GET');
        self::assertStringContainsString('"linked":false', $this->body());
        self::assertStringNotContainsString($assignment, $this->body());
        $this->member('PUT', '/availability', $this->availability($this->volunteer, 0));
        self::assertSame(404, $this->code());
        self::assertSame('Linked volunteer profile was not found.', $this->message());
    }

    public function testResponseUsesOwnInvitationVersionsAndDoesNotClaimReadiness(): void
    {
        $assignment = $this->book($this->volunteer, 'PROPOSED');
        $invitation = $this->invite($assignment);
        $this->member('GET');
        self::assertSame(['id' => $invitation, 'version' => 0, 'status' => 'PENDING', 'expiresAt' => self::at($this->start)], $this->json()['items'][0]['invitation']);
        $this->member('POST', "/invitations/$invitation/respond", ['expectedVersion' => 0, 'decision' => 'ACCEPTED']);
        self::assertSame(204, $this->code(), $this->body());
        $this->member('GET');
        self::assertStringContainsString('CONFIRMED', $this->body());
        self::assertStringContainsString('"ready":false', $this->body());
        $this->member('POST', "/invitations/$invitation/respond", ['expectedVersion' => 0, 'decision' => 'DECLINED']);
        self::assertSame(409, $this->code());
        $other = $this->add('/volunteers', ['personId' => $this->person('Other')]);
        $otherInvitation = $this->invite($this->book($other, 'PROPOSED'));
        $this->member('POST', "/invitations/$otherInvitation/respond", ['expectedVersion' => 0, 'decision' => 'ACCEPTED']);
        self::assertSame(404, $this->code());
        self::assertSame('Invitation was not found.', $this->message());
    }

    public function testExpiryIsReadOnlyAndChangedDutyCannotBeAccepted(): void
    {
        $invitation = $this->invite($this->book($this->volunteer, 'PROPOSED'));
        $this->connection->executeStatement("UPDATE serving_invitations SET expires_at = now() - interval '1 minute' WHERE id = ?", [$invitation]);
        $this->member('GET');
        self::assertStringContainsString('EXPIRED', $this->body());
        self::assertSame('PENDING', $this->connection->fetchOne('SELECT status FROM serving_invitations WHERE id = ?', [$invitation]));
        $this->member('POST', "/invitations/$invitation/respond", ['expectedVersion' => 0, 'decision' => 'ACCEPTED']);
        self::assertSame(409, $this->code());
        self::assertSame('This invitation expired or the shift changed. Request a new invitation.', $this->message());
    }

    public function testAvailabilityIsVersionedAndNeverCancelsAnExistingDuty(): void
    {
        $this->ready();
        $assignment = $this->book($this->volunteer, 'CONFIRMED');
        $version = $this->volunteerVersion();
        $payload = $this->availability($this->volunteer, $version, [['startsAt' => self::at($this->start), 'endsAt' => self::at($this->end), 'available' => false]]);
        $this->member('PUT', '/availability', $payload);
        self::assertSame(204, $this->code(), $this->body());
        $this->member('GET');
        foreach ([$assignment, 'CONFIRMED', '"ready":false', '"available":false'] as $expected) {
            self::assertStringContainsString($expected, $this->body());
        }
        $this->member('PUT', '/availability', $payload);
        self::assertSame(409, $this->code());
        $this->member('PUT', '/availability', $this->availability($this->volunteer, $version + 1, [['startsAt' => self::at($this->end), 'endsAt' => self::at($this->start), 'available' => false]]));
        self::assertSame(400, $this->code());
        $this->connection->executeStatement("UPDATE serving_volunteers SET status = 'PAUSED' WHERE id = ?", [$this->volunteer]);
        $this->member('PUT', '/availability', [...$payload, 'expectedVersion' => $version + 1]);
        self::assertSame(403, $this->code());
    }

    public function testBoundedPagingAndRevokedServingPermissionAreEnforced(): void
    {
        $this->book($this->volunteer, 'PROPOSED');
        $next = $this->add('/shifts', $this->draft(self::uuid(), $this->end, $this->end->modify('+3600 seconds'), 1));
        $this->add('/assignments', ['requestId' => self::uuid(), 'shiftId' => $next, 'expectedVersion' => 0, 'volunteerId' => $this->volunteer, 'status' => 'CONFIRMED']);
        $this->member('GET', '?limit=1');
        self::assertTrue($this->json()['hasMore']);
        $this->member('GET', '?limit=1&offset=1');
        self::assertFalse($this->json()['hasMore']);
        $this->connection->executeStatement("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE code IN ('volunteering.serve', 'volunteering.manage'))");
        $this->member('GET');
        self::assertStringContainsString('"permitted":false', $this->body());
        self::assertStringNotContainsString('Welcomer', $this->body());
        $this->member('PUT', '/availability', $this->availability($this->volunteer, 0));
        self::assertSame(403, $this->code());
    }

    public function testOwnServingDoesNotNeedAnyStaffPermission(): void
    {
        $invitation = $this->invite($this->book($this->volunteer, 'PROPOSED'));
        $this->connection->executeStatement("DELETE FROM role_permissions WHERE permission_id <> (SELECT id FROM permissions WHERE code = 'volunteering.serve')");
        $this->member('GET');
        self::assertSame(200, $this->code());
        $this->serving('GET', '/volunteers');
        self::assertSame(403, $this->code());
        $this->member('POST', "/invitations/$invitation/respond", ['expectedVersion' => 0, 'decision' => 'DECLINED']);
        self::assertSame(204, $this->code(), $this->body());
        $this->member('PUT', '/availability', $this->availability($this->volunteer, 0));
        self::assertSame(204, $this->code(), $this->body());
    }

    public function testAnOldDraftCannotApplyToADifferentLinkedVolunteerWithTheSameVersion(): void
    {
        $other = $this->add('/volunteers', ['personId' => $this->person('Other')]);
        $this->connection->executeStatement('UPDATE serving_volunteers SET account_id = NULL WHERE id = ?', [$this->volunteer]);
        $this->connection->executeStatement('UPDATE serving_volunteers SET account_id = ? WHERE id = ?', [$this->adminId, $other]);
        $this->member('PUT', '/availability', $this->availability($this->volunteer, 0));
        self::assertSame(409, $this->code());
        self::assertSame('Your volunteer profile changed. Refresh before editing availability.', $this->message());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT version FROM serving_volunteers WHERE id = ?', [$other]));
    }

    public function testMalformedAvailabilityIsRejected(): void
    {
        foreach ([
            ['expectedVersion' => 0, 'weekly' => [], 'exceptions' => []],
            [...$this->availability($this->volunteer, 0), 'weekly' => 'monday'],
            [...$this->availability($this->volunteer, 0), 'weekly' => [['dayOfWeek' => 1, 'startsAt' => '25:00', 'endsAt' => '10:00', 'timeZone' => 'UTC', 'validFrom' => '2026-01-01', 'validUntil' => '2027-01-01']]],
            [...$this->availability($this->volunteer, 0), 'weekly' => [['dayOfWeek' => 8, 'startsAt' => '09:00', 'endsAt' => '10:00', 'timeZone' => 'UTC', 'validFrom' => '2026-01-01', 'validUntil' => '2027-01-01']]],
            [...$this->availability($this->volunteer, 0), 'weekly' => [['dayOfWeek' => 1, 'startsAt' => '09:00', 'endsAt' => '10:00', 'timeZone' => 'Mars/Base', 'validFrom' => '2026-01-01', 'validUntil' => '2027-01-01']]],
            ['volunteerId' => $this->volunteer, 'weekly' => [], 'exceptions' => []],
        ] as $body) {
            $this->member('PUT', '/availability', $body);
            self::assertSame(400, $this->code(), json_encode($body, \JSON_THROW_ON_ERROR));
        }
        self::assertSame(0, $this->volunteerVersion());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Safeguarding\Presentation;

use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;

final class SafeguardingApiTest extends ChildrenApiTestCase
{
    private string $child;
    private string $guardian;
    private string $programme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->child = $this->person('Child', $this->yearsAgo(8));
        $this->guardian = $this->person('Parent', $this->yearsAgo(35));
        $this->programme = $this->programme();
    }

    /** @return array<string, mixed> */
    private function authorityBody(?string $from = null, ?string $until = null): array
    {
        return ['childId' => $this->child, 'guardianId' => $this->guardian, 'validFrom' => $from ?? $this->instant('-1 day'), 'validUntil' => $until ?? $this->instant('+1 year'), 'evidenceReference' => 'Birth certificate'];
    }

    private function instant(string $modify): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify($modify)->format('Y-m-d\TH:i:s\Z');
    }

    private function verified(): string
    {
        return $this->create('/guardian-authorities', $this->authorityBody());
    }

    /** @return array<string, mixed> */
    private function pickupBody(string $authority, ?string $from = null, ?string $until = null): array
    {
        return ['programmeId' => $this->programme, 'childId' => $this->child, 'collectorId' => $this->guardian, 'authorityId' => $authority, 'validFrom' => $from ?? $this->instant('+1 hour'), 'validUntil' => $until ?? $this->instant('+2 hours'), 'evidenceReference' => 'ID checked'];
    }

    public function testVerifyingAGuardianIsAuditedAndReadBack(): void
    {
        $id = $this->verified();

        self::assertSame(1, $this->rows('children_audit', "action = 'guardian.authority.verified' AND target_id = '$id' AND actor_id = '{$this->adminId}'"));
        self::assertSame(1, $this->rows('children_child_guards', "child_id = '{$this->child}'"));
        $this->call('GET', "/api/children/safeguarding/{$this->child}", $this->admin);
        self::assertSame(200, $this->code());
        $view = $this->json();
        self::assertSame([], $view['consents']);
        self::assertSame([], $view['pickupAuthorizations']);
        $authority = $view['authorities'][0];
        self::assertSame([$id, $this->child, $this->guardian, 'Birth certificate', $this->adminId, null, null, 0], [$authority['id'], $authority['childId'], $authority['guardianId'], $authority['evidenceReference'], $authority['verifiedBy'], $authority['revokedAt'], $authority['revocationReason'], $authority['version']]);
        self::assertMatchesRegularExpression('/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(\.\d+)?Z\z/', $authority['validFrom']);
    }

    public function testEveryRouteNeedsTheSafeguardingPermission(): void
    {
        $calls = [
            ['POST', '/api/children/guardian-authorities', $this->authorityBody()],
            ['POST', '/api/children/guardian-authorities/revoke', ['id' => $this->child, 'expectedVersion' => 0, 'reason' => 'x']],
            ['POST', '/api/children/pickup-authorizations', $this->pickupBody($this->child)],
            ['POST', '/api/children/pickup-authorizations/revoke', ['id' => $this->child, 'expectedVersion' => 0, 'reason' => 'x']],
            ['GET', "/api/children/safeguarding/{$this->child}", null],
        ];
        $this->accountWith('manager', ['children.manage', 'children.read']);
        $manager = $this->login('manager');
        foreach ($calls as [$method, $path, $body]) {
            $this->call($method, $path, null, $body);
            self::assertSame(401, $this->code(), "anonymous $path");
            foreach ([$this->reader, $manager] as $token) {
                $this->call($method, $path, $token, $body);
                self::assertSame(403, $this->code(), "without children.safeguarding $path");
            }
        }
        self::assertSame(0, $this->rows('children_guardian_authorities'));
    }

    public function testGuardianInputIsChecked(): void
    {
        $valid = $this->authorityBody();
        $child = $this->person('Teen', $this->yearsAgo(17));
        $notAdult = $this->person('Sibling', $this->yearsAgo(16));
        $bad = [
            'end before start' => ['validUntil' => $this->instant('-2 days')] + $valid,
            'zero length' => ['validUntil' => $valid['validFrom']] + $valid,
            'malformed instant' => ['validFrom' => '2026-01-01'] + $valid,
            'missing instant' => array_diff_key($valid, ['validUntil' => 1]),
            'missing evidence' => ['evidenceReference' => ''] + $valid,
            'evidence too long' => ['evidenceReference' => str_repeat('x', 256)] + $valid,
            'bad child id' => ['childId' => 'nope'] + $valid,
            'guardian is the child' => ['guardianId' => $this->child] + $valid,
            'guardian is a minor' => ['guardianId' => $notAdult] + $valid,
            'child is an adult' => ['childId' => $this->guardian, 'guardianId' => $child] + $valid,
        ];
        foreach ($bad as $name => $body) {
            $this->call('POST', '/api/children/guardian-authorities', $this->admin, $body);
            self::assertSame(400, $this->code(), $name);
        }
        $this->call('POST', '/api/children/guardian-authorities', $this->admin, ['guardianId' => '00000000-0000-4000-8000-0000000000aa'] + $valid);
        self::assertSame(404, $this->code());
        self::assertSame('Person was not found.', $this->message());
        self::assertSame(0, $this->rows('children_guardian_authorities'));
        self::assertSame(0, $this->rows('children_child_guards'), 'failed commands leave no trace');
    }

    public function testRevokingAGuardianNeedsTheCurrentVersionAndHappensOnce(): void
    {
        $id = $this->verified();

        $this->call('POST', '/api/children/guardian-authorities/revoke', $this->admin, ['id' => $id, 'reason' => 'x']);
        self::assertSame(400, $this->code(), 'a missing version is never a silent zero');
        $this->call('POST', '/api/children/guardian-authorities/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 5, 'reason' => 'x']);
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/children/guardian-authorities/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 0, 'reason' => '  ']);
        self::assertSame(400, $this->code(), 'a reason is required');
        $this->call('POST', '/api/children/guardian-authorities/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 0, 'reason' => 'Court order']);
        self::assertSame(204, $this->code());
        $this->call('POST', '/api/children/guardian-authorities/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 1, 'reason' => 'Again']);
        self::assertSame(409, $this->code());
        self::assertSame('This record is already revoked.', $this->message());
        $this->call('POST', '/api/children/guardian-authorities/revoke', $this->admin, ['id' => '00000000-0000-4000-8000-0000000000aa', 'expectedVersion' => 0, 'reason' => 'x']);
        self::assertSame(404, $this->code());
        self::assertSame(1, $this->rows('children_audit', "action = 'guardianauthority.revoked' AND target_id = '$id' AND reason = 'Court order'"));
        $this->call('GET', "/api/children/safeguarding/{$this->child}", $this->admin);
        self::assertSame([1, 'Court order'], [$this->json()['authorities'][0]['version'], $this->json()['authorities'][0]['revocationReason']]);
        self::assertNotNull($this->json()['authorities'][0]['revokedAt']);
    }

    public function testPickupNeedsAuthorityOfTheSameChildCoveringThePeriod(): void
    {
        $authority = $this->verified();
        $otherChild = $this->person('Other', $this->yearsAgo(6));
        $this->call('POST', '/api/children/guardian-authorities', $this->admin, ['childId' => $otherChild] + $this->authorityBody());
        $otherAuthority = (string) $this->json()['id'];

        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, $this->pickupBody($authority, $this->instant('+1 hour'), $this->instant('+2 years')));
        self::assertSame(400, $this->code(), 'runs past the authority');
        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, $this->pickupBody($authority, $this->instant('-2 days'), $this->instant('+2 hours')));
        self::assertSame(400, $this->code(), 'starts before the authority');
        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, $this->pickupBody($otherAuthority));
        self::assertSame(400, $this->code(), 'the authority belongs to another child');
        self::assertSame(0, $this->rows('children_pickup_authorizations'));

        $id = $this->create('/pickup-authorizations', $this->pickupBody($authority));
        self::assertSame(1, $this->rows('children_audit', "action = 'pickup.authorized' AND target_id = '$id'"));
        $this->call('GET', "/api/children/safeguarding/{$this->child}", $this->admin);
        $grant = $this->json()['pickupAuthorizations'][0];
        self::assertSame([$id, $this->programme, $this->child, $this->guardian, $authority, $this->adminId, null, 0], [$grant['id'], $grant['programmeId'], $grant['childId'], $grant['collectorId'], $grant['authorityId'], $grant['recordedBy'], $grant['revokedAt'], $grant['version']]);
    }

    public function testPickupReferencesAndCollectorAreChecked(): void
    {
        $authority = $this->verified();
        $valid = $this->pickupBody($authority);
        $minor = $this->person('Minor', $this->yearsAgo(15));

        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, ['programmeId' => '00000000-0000-4000-8000-0000000000aa'] + $valid);
        self::assertSame(404, $this->code());
        self::assertSame('Programme was not found.', $this->message());
        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, ['authorityId' => '00000000-0000-4000-8000-0000000000aa'] + $valid);
        self::assertSame(404, $this->code());
        self::assertSame('GuardianAuthority was not found.', $this->message());
        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, ['collectorId' => $minor] + $valid);
        self::assertSame(400, $this->code(), 'collectors are adults');
        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, ['collectorId' => $this->child] + $valid);
        self::assertSame(400, $this->code(), 'a child cannot collect themselves');
        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, array_diff_key($valid, ['programmeId' => 1]));
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('children_pickup_authorizations'));
    }

    public function testOverlappingActiveGrantsForOneCollectorAreRefusedUntilRevoked(): void
    {
        $authority = $this->verified();
        $first = $this->create('/pickup-authorizations', $this->pickupBody($authority));

        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, $this->pickupBody($authority, $this->instant('+90 minutes'), $this->instant('+3 hours')));
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/children/pickup-authorizations/revoke', $this->admin, ['id' => $first, 'expectedVersion' => 0, 'reason' => 'Plans changed']);
        self::assertSame(204, $this->code());
        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, $this->pickupBody($authority, $this->instant('+90 minutes'), $this->instant('+3 hours')));
        self::assertSame(201, $this->code(), 'a revoked grant no longer blocks');
        self::assertSame(1, $this->rows('children_audit', "action = 'pickupauthorization.revoked' AND target_id = '$first'"));
    }

    public function testRevokingAGrantNeedsTheCurrentVersionAndHappensOnce(): void
    {
        $id = $this->create('/pickup-authorizations', $this->pickupBody($this->verified()));

        $this->call('POST', '/api/children/pickup-authorizations/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 3, 'reason' => 'x']);
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/children/pickup-authorizations/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 0]);
        self::assertSame(400, $this->code(), 'a reason is required');
        $this->call('POST', '/api/children/pickup-authorizations/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 0, 'reason' => 'Moved']);
        self::assertSame(204, $this->code());
        $this->call('POST', '/api/children/pickup-authorizations/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 1, 'reason' => 'Again']);
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/children/pickup-authorizations/revoke', $this->admin, ['id' => '00000000-0000-4000-8000-0000000000aa', 'expectedVersion' => 0, 'reason' => 'x']);
        self::assertSame(404, $this->code());
        self::assertSame('PickupAuthorization was not found.', $this->message());
    }

    public function testARevokedAuthorityNoLongerBacksNewGrants(): void
    {
        $authority = $this->verified();
        $this->call('POST', '/api/children/guardian-authorities/revoke', $this->admin, ['id' => $authority, 'expectedVersion' => 0, 'reason' => 'Order']);

        $this->call('POST', '/api/children/pickup-authorizations', $this->admin, $this->pickupBody($authority));

        self::assertSame(400, $this->code());
    }

    public function testMalformedPathIdentifierIsABadRequest(): void
    {
        $this->call('GET', '/api/children/safeguarding/not-a-uuid', $this->admin);

        self::assertSame(400, $this->code());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Safeguarding\Presentation;

use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;

final class ConsentAndReadinessApiTest extends ChildrenApiTestCase
{
    private string $programme;
    private string $group;
    private string $guardian;
    private string $collector;
    private string $child;
    private string $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->programme = $this->programme();
        $this->group = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $this->guardian = $this->person('Parent', $this->yearsAgo(35));
        $this->collector = $this->person('Uncle', $this->yearsAgo(40));
        $this->child = $this->person('Child', $this->yearsAgo(8));
        $this->policy = $this->publish(0, 'v1');
    }

    private function publish(int $version, string $label, ?string $hash = null): string
    {
        return $this->create('/consent-policies', ['programmeId' => $this->programme, 'expectedVersion' => $version, 'label' => $label, 'documentReference' => "docs/$label", 'contentHash' => $hash ?? str_repeat('a', 64)]);
    }

    private function now(string $modify = ''): string
    {
        $time = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return ('' === $modify ? $time : $time->modify($modify))->format('Y-m-d\TH:i:s.u\Z');
    }

    private function authority(string $child): string
    {
        return $this->create('/guardian-authorities', ['childId' => $child, 'guardianId' => $this->guardian, 'validFrom' => $this->now('-1 day'), 'validUntil' => $this->now('+1 year'), 'evidenceReference' => 'private/guardian']);
    }

    /** @return array<string, mixed> */
    private function consentBody(string $child, string $authority, ?string $policy = null): array
    {
        return ['childId' => $child, 'programmeId' => $this->programme, 'policyId' => $policy ?? $this->policy, 'authorityId' => $authority, 'validFrom' => $this->now('-1 hour'), 'validUntil' => $this->now('+6 months'), 'acceptedAt' => $this->now(), 'evidenceReference' => 'private/consent'];
    }

    private function collect(string $child, string $authority): string
    {
        return $this->create('/pickup-authorizations', ['programmeId' => $this->programme, 'childId' => $child, 'collectorId' => $this->collector, 'authorityId' => $authority, 'validFrom' => $this->now('-1 hour'), 'validUntil' => $this->now('+6 months'), 'evidenceReference' => 'private/pickup']);
    }

    private function place(string $child): void
    {
        $enrolment = $this->enrol($this->programme, $child);
        $this->create('/class-assignments', ['enrolmentId' => $enrolment, 'classId' => $this->group, 'validFrom' => $this->today(), 'validUntil' => $this->today('+1 month')]);
    }

    /** @return array<string, bool> */
    private function flags(string $child): array
    {
        $this->call('GET', "/api/children/programmes/{$this->programme}/children/$child/readiness", $this->reader);
        self::assertSame(200, $this->code(), $this->message());

        return array_column($this->json()['steps'], 'complete', 'code');
    }

    public function testPublishingAPolicyMakesItCurrentAndAdvancesTheProgrammeVersion(): void
    {
        self::assertSame(1, $this->rows('children_audit', "action = 'consent.policy.published' AND target_id = '{$this->policy}'"));
        $this->call('GET', "/api/children/programmes/{$this->programme}", $this->reader);
        self::assertSame([$this->policy, 1], [$this->json()['currentPolicyId'], $this->json()['version']]);
        $this->call('GET', "/api/children/consent-policies/{$this->policy}", $this->reader);
        $policy = $this->json();
        self::assertSame([$this->policy, $this->programme, 'v1', 'docs/v1', str_repeat('a', 64)], [$policy['id'], $policy['programmeId'], $policy['label'], $policy['documentReference'], $policy['contentHash']]);
        self::assertMatchesRegularExpression('/Z\z/', $policy['createdAt']);
    }

    public function testPublishingIsCheckedAndNeedsManage(): void
    {
        $body = ['programmeId' => $this->programme, 'expectedVersion' => 1, 'label' => 'v2', 'documentReference' => 'docs/v2', 'contentHash' => str_repeat('b', 64)];
        $bad = [
            'stale version' => [['expectedVersion' => 0] + $body, 409],
            'duplicate label' => [['label' => 'v1'] + $body, 409],
            'upper-case hash' => [['contentHash' => str_repeat('B', 64)] + $body, 400],
            'short hash' => [['contentHash' => 'abc'] + $body, 400],
            'missing hash' => [array_diff_key($body, ['contentHash' => 1]), 400],
            'blank label' => [['label' => ' '] + $body, 400],
            'long label' => [['label' => str_repeat('x', 41)] + $body, 400],
            'missing version' => [array_diff_key($body, ['expectedVersion' => 1]), 400],
            'unknown programme' => [['programmeId' => '00000000-0000-4000-8000-0000000000aa'] + $body, 404],
        ];
        foreach ($bad as $name => [$payload, $status]) {
            $this->call('POST', '/api/children/consent-policies', $this->admin, $payload);
            self::assertSame($status, $this->code(), $name);
        }
        self::assertSame(1, $this->rows('children_consent_policies'), 'failures leave nothing behind');
        $this->call('POST', '/api/children/consent-policies', $this->reader, $body);
        self::assertSame(403, $this->code());
        $this->call('POST', '/api/children/consent-policies', null, $body);
        self::assertSame(401, $this->code());
        $this->call('GET', "/api/children/consent-policies/{$this->policy}");
        self::assertSame(401, $this->code());
        $this->call('GET', '/api/children/consent-policies/00000000-0000-4000-8000-0000000000aa', $this->reader);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/children/consent-policies/nope', $this->reader);
        self::assertSame(400, $this->code());
    }

    public function testConsentIsRecordedAuditedAndReadBack(): void
    {
        $authority = $this->authority($this->child);

        $id = $this->create('/consents', $this->consentBody($this->child, $authority));

        self::assertSame(1, $this->rows('children_audit', "action = 'consent.recorded' AND target_id = '$id'"));
        $this->call('GET', "/api/children/safeguarding/{$this->child}", $this->admin);
        $consent = $this->json()['consents'][0];
        self::assertSame([$id, $this->programme, $this->policy, $this->child, $authority, $this->adminId, 'private/consent', null, null, 0], [$consent['id'], $consent['programmeId'], $consent['policyId'], $consent['childId'], $consent['authorityId'], $consent['recordedBy'], $consent['evidenceReference'], $consent['revokedAt'], $consent['revocationReason'], $consent['version']]);
        self::assertMatchesRegularExpression('/Z\z/', $consent['acceptedAt']);
    }

    public function testConsentRulesAreEnforced(): void
    {
        $authority = $this->authority($this->child);
        $other = $this->person('Other', $this->yearsAgo(6));
        $otherAuthority = $this->create('/guardian-authorities', ['childId' => $other, 'guardianId' => $this->guardian, 'validFrom' => $this->now('-1 day'), 'validUntil' => $this->now('+1 year'), 'evidenceReference' => 'x']);
        $otherProgramme = $this->programme('Midweek');
        $otherPolicy = $this->create('/consent-policies', ['programmeId' => $otherProgramme, 'expectedVersion' => 0, 'label' => 'v1', 'documentReference' => 'd', 'contentHash' => str_repeat('c', 64)]);
        $valid = $this->consentBody($this->child, $authority);
        $bad = [
            'future acceptance' => ['acceptedAt' => $this->now('+1 hour')] + $valid,
            'acceptance before policy' => ['acceptedAt' => $this->now('-1 hour')] + $valid,
            'outlasts authority' => ['validUntil' => $this->now('+2 years')] + $valid,
            'authority of another child' => ['authorityId' => $otherAuthority] + $valid,
            'policy of another programme' => ['policyId' => $otherPolicy] + $valid,
            'end before start' => ['validUntil' => $this->now('-2 hours')] + $valid,
            'missing evidence' => ['evidenceReference' => ''] + $valid,
            'missing acceptance' => array_diff_key($valid, ['acceptedAt' => 1]),
            'malformed instant' => ['validFrom' => 'yesterday'] + $valid,
        ];
        foreach ($bad as $name => $body) {
            $this->call('POST', '/api/children/consents', $this->admin, $body);
            self::assertSame(400, $this->code(), $name);
        }
        $this->call('POST', '/api/children/consents', $this->admin, ['authorityId' => '00000000-0000-4000-8000-0000000000aa'] + $valid);
        self::assertSame(404, $this->code());
        $this->call('POST', '/api/children/consents', $this->admin, ['policyId' => '00000000-0000-4000-8000-0000000000aa'] + $valid);
        self::assertSame(404, $this->code());
        $this->call('POST', '/api/children/consents', $this->reader, $valid);
        self::assertSame(403, $this->code());
        self::assertSame(0, $this->rows('children_consents'));
    }

    public function testRevokingConsentNeedsTheCurrentVersionAndHappensOnce(): void
    {
        $id = $this->create('/consents', $this->consentBody($this->child, $this->authority($this->child)));

        $this->call('POST', '/api/children/consents/revoke', $this->admin, ['id' => $id, 'reason' => 'x']);
        self::assertSame(400, $this->code());
        $this->call('POST', '/api/children/consents/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 4, 'reason' => 'x']);
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/children/consents/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 0]);
        self::assertSame(400, $this->code(), 'a reason is required');
        $this->call('POST', '/api/children/consents/revoke', $this->reader, ['id' => $id, 'expectedVersion' => 0, 'reason' => 'x']);
        self::assertSame(403, $this->code());
        $this->call('POST', '/api/children/consents/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 0, 'reason' => 'Withdrawn']);
        self::assertSame(204, $this->code());
        $this->call('POST', '/api/children/consents/revoke', $this->admin, ['id' => $id, 'expectedVersion' => 1, 'reason' => 'Again']);
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/children/consents/revoke', $this->admin, ['id' => '00000000-0000-4000-8000-0000000000aa', 'expectedVersion' => 0, 'reason' => 'x']);
        self::assertSame(404, $this->code());
        self::assertSame(1, $this->rows('children_audit', "action = 'consent.revoked' AND target_id = '$id' AND reason = 'Withdrawn'"));
    }

    public function testPreparationUsesCurrentEvidenceAndExposesNoCareRecords(): void
    {
        $this->place($this->child);
        $authority = $this->authority($this->child);
        $this->create('/consents', $this->consentBody($this->child, $authority));
        $this->collect($this->child, $authority);

        $this->call('GET', "/api/children/programmes/{$this->programme}/children/{$this->child}/readiness", $this->reader);

        self::assertSame(200, $this->code());
        $view = $this->json();
        self::assertSame([true, 7, 7, $this->today(), 'Africa/Johannesburg'], [$view['ready'], $view['completed'], $view['total'], $view['on'], $view['timeZone']]);
        self::assertSame(['IDENTITY', 'ENROLMENT', 'CLASS', 'POLICY', 'GUARDIAN', 'CONSENT', 'COLLECTOR'], array_column($view['steps'], 'code'));
        self::assertSame($this->child, $view['enrolment']['childId']);
        $body = (string) $this->client->getResponse()->getContent();
        foreach (['evidenceReference', 'guardianId', 'collectorId', 'private/', 'pickupCode'] as $secret) {
            self::assertStringNotContainsString($secret, $body);
        }
        $this->call('GET', "/api/children/programmes/{$this->programme}/children", $this->reader);
        $row = $this->json()['children'][0];
        self::assertSame(['ready' => true, 'completed' => 7, 'total' => 7], $row['readiness']);
        self::assertStringNotContainsString('steps', (string) $this->client->getResponse()->getContent());
    }

    public function testStepsCompleteAsEvidenceIsRecorded(): void
    {
        self::assertSame(['IDENTITY' => true, 'ENROLMENT' => false, 'CLASS' => false, 'POLICY' => true, 'GUARDIAN' => false, 'CONSENT' => false, 'COLLECTOR' => false], $this->flags($this->child));
        $this->place($this->child);
        $authority = $this->authority($this->child);
        $this->create('/consents', $this->consentBody($this->child, $authority));
        $flags = $this->flags($this->child);
        self::assertSame([true, true, true, true, false], [$flags['ENROLMENT'], $flags['CLASS'], $flags['GUARDIAN'], $flags['CONSENT'], $flags['COLLECTOR']]);
        $this->collect($this->child, $authority);
        $this->call('GET', "/api/children/programmes/{$this->programme}/children/{$this->child}/readiness", $this->reader);
        self::assertTrue($this->json()['ready']);
    }

    public function testANewPolicyOrARevokedAuthorityInvalidatesPreparationImmediately(): void
    {
        $this->place($this->child);
        $authority = $this->authority($this->child);
        $this->create('/consents', $this->consentBody($this->child, $authority));
        $this->collect($this->child, $authority);

        $this->publish(1, 'v2');
        $flags = $this->flags($this->child);
        self::assertSame([false, true, true], [$flags['CONSENT'], $flags['GUARDIAN'], $flags['COLLECTOR']]);

        $this->call('POST', '/api/children/guardian-authorities/revoke', $this->admin, ['id' => $authority, 'expectedVersion' => 0, 'reason' => 'Withdrawn']);
        $flags = $this->flags($this->child);
        self::assertSame([false, false, false], [$flags['GUARDIAN'], $flags['CONSENT'], $flags['COLLECTOR']]);
    }

    public function testFutureEnrolmentsAreNotCurrentAndUnknownAgesAreNotAssumedEligible(): void
    {
        $future = $this->person('Future', $this->yearsAgo(8));
        $this->enrol($this->programme, $future, $this->today('+1 day'), $this->today('+1 year'));
        $flags = $this->flags($future);
        self::assertSame([false, false], [$flags['ENROLMENT'], $flags['CLASS']]);
        self::assertFalse($this->flags($this->person('Unknown age', null))['IDENTITY']);
        self::assertFalse($this->flags($this->guardian)['IDENTITY'], 'adults are not children');
    }

    public function testReadinessReferencesAndPermissionsAreChecked(): void
    {
        $path = "/api/children/programmes/{$this->programme}/children/{$this->child}/readiness";

        $this->call('GET', $path);
        self::assertSame(401, $this->code());
        $this->accountWith('nobody', []);
        $this->call('GET', $path, $this->login('nobody'));
        self::assertSame(403, $this->code());
        $this->call('GET', "/api/children/programmes/{$this->programme}/children/00000000-0000-4000-8000-0000000000aa/readiness", $this->reader);
        self::assertSame(404, $this->code());
        $this->call('GET', "/api/children/programmes/00000000-0000-4000-8000-0000000000aa/children/{$this->child}/readiness", $this->reader);
        self::assertSame(404, $this->code());
        $this->call('GET', "/api/children/programmes/nope/children/{$this->child}/readiness", $this->reader);
        self::assertSame(400, $this->code());
    }
}

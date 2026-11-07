<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Safeguarding\Presentation;

use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

/** The safeguarding worklist against the real schema: current coverage, lapsing evidence and care review dates. */
final class SafeguardingFollowUpApiTest extends ChildrenApiTestCase
{
    private const string URI = '/api/children/safeguarding-follow-up';

    private string $programme;
    private string $guardian;
    private string $collector;
    private string $child;
    private string $authority;
    private string $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->programme = $this->programme();
        $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $this->policy = $this->create('/consent-policies', ['programmeId' => $this->programme, 'expectedVersion' => 0, 'label' => 'v1', 'documentReference' => 'docs/v1', 'contentHash' => str_repeat('a', 64)]);
        $this->guardian = $this->person('Guardian', $this->yearsAgo(35));
        $this->collector = $this->person('Collector', $this->yearsAgo(40));
        $this->child = $this->person('Child', $this->yearsAgo(8));
        $this->authority = $this->create('/guardian-authorities', ['childId' => $this->child, 'guardianId' => $this->guardian, 'validFrom' => $this->at('-1 day'), 'validUntil' => $this->at('+1 year'), 'evidenceReference' => 'private/guardian']);
        $this->consent($this->child, $this->authority);
        $this->create('/pickup-authorizations', ['programmeId' => $this->programme, 'childId' => $this->child, 'collectorId' => $this->collector, 'authorityId' => $this->authority, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'evidenceReference' => 'private/pickup']);
        $this->enrol($this->programme, $this->child);
    }

    private function at(string $modify): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('' === $modify ? '+0 seconds' : $modify)->format('Y-m-d\TH:i:s.u\Z');
    }

    private function consent(string $child, string $authority): void
    {
        $this->create('/consents', ['childId' => $child, 'programmeId' => $this->programme, 'policyId' => $this->policy, 'authorityId' => $authority, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'acceptedAt' => $this->at(''), 'evidenceReference' => 'private/consent']);
    }

    private function raw(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    private function get(string $query = '', ?string $token = null): void
    {
        $this->call('GET', self::URI.$query, $token ?? $this->admin);
    }

    public function testFullyCoveredChildrenRaiseNothing(): void
    {
        $this->get();

        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([0, 0, false, 'Africa/Johannesburg'], [$this->json()['total'], $this->json()['summary']['children'], false, $this->json()['timeZone']]);
        self::assertMatchesRegularExpression('/\A\d{4}-\d\d-\d\d\z/', $this->json()['through']);
    }

    public function testCurrentCoverageIsGroupedBeforePagingAndPolicyChangesRequireNewConsent(): void
    {
        $missing = $this->person('Missing', $this->yearsAgo(8));
        $this->enrol($this->programme, $missing);
        $audits = $this->rows('children_audit');

        $this->get('?search=Missing&limit=1');
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([3, 3, 1, 'Missing Mabasa', 1], [$this->json()['total'], $this->json()['summary']['missing'], $this->json()['summary']['children'], $this->json()['issues'][0]['childName'], count($this->json()['issues'])]);
        self::assertStringNotContainsString('evidenceReference', $this->raw());
        $this->get('?search=Missing&kind=PICKUP');
        self::assertSame([1, 3], [$this->json()['total'], $this->json()['summary']['missing']], 'the summary covers every issue type before filtering');
        self::assertSame($audits, $this->rows('children_audit'), 'a read audits nothing');

        $this->create('/consent-policies', ['programmeId' => $this->programme, 'expectedVersion' => 1, 'label' => 'Updated policy', 'documentReference' => 'consent/next', 'contentHash' => str_repeat('b', 64)]);
        $this->get('?search=Child&kind=CONSENT');
        self::assertSame(1, $this->json()['total']);
        self::assertSame(['MISSING', 'CONSENT'], [$this->json()['issues'][0]['state'], $this->json()['issues'][0]['kind']]);
        self::assertStringContainsString('current programme policy', $this->json()['issues'][0]['message']);
    }

    public function testAProgrammeWithoutAPolicyAsksForOneInsteadOfConsent(): void
    {
        $bare = $this->programme('No policy yet');
        $this->enrol($bare, $this->child);

        $this->get('?kind=CONSENT&state=MISSING');

        self::assertStringContainsString('Publish a programme consent policy', $this->raw());
    }

    public function testAuthorityExpiryLimitsDependentEvidenceAndAlternativeCoveragePreventsFalseAlerts(): void
    {
        $this->connection->executeStatement("UPDATE children_guardian_authorities SET valid_until = now() + interval '5 days' WHERE id = ?", [$this->authority]);
        $this->get('?days=7');
        self::assertSame([3, 3], [$this->json()['total'], $this->json()['summary']['expiring']]);
        self::assertEqualsCanonicalizing(['GUARDIAN', 'CONSENT', 'PICKUP'], array_column($this->json()['issues'], 'kind'));

        $alternative = $this->create('/guardian-authorities', ['childId' => $this->child, 'guardianId' => $this->collector, 'validFrom' => $this->at('-1 day'), 'validUntil' => $this->at('+1 year'), 'evidenceReference' => 'Alternative authority']);
        $this->consent($this->child, $alternative);
        $this->create('/pickup-authorizations', ['programmeId' => $this->programme, 'childId' => $this->child, 'collectorId' => $this->guardian, 'authorityId' => $alternative, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'evidenceReference' => 'Alternative collector']);
        $this->get('?days=7');
        self::assertSame(0, $this->json()['total'], 'a second guardian covers the child');

        $this->connection->executeStatement('UPDATE children_guardian_authorities SET revoked_at = now() WHERE id = ?', [$alternative]);
        $this->connection->executeStatement("UPDATE children_guardian_authorities SET valid_until = now() - interval '1 second' WHERE id = ?", [$this->authority]);
        $this->get();
        self::assertSame([3, 3], [$this->json()['total'], $this->json()['summary']['expired']]);
    }

    public function testCareFollowUpExposesOnlyDatesAndOnlyToSupportUsers(): void
    {
        $this->call('PUT', '/api/children/support', $this->admin, ['childId' => $this->child, 'expectedVersion' => 0, 'allergies' => 'Peanut secret', 'careInstructions' => 'Private care', 'restrictedNotes' => 'Restricted secret', 'reviewOn' => $this->today(), 'retainUntil' => $this->today('+1 year')]);
        self::assertSame(204, $this->code());
        $audits = $this->rows('children_audit');

        $this->get();
        self::assertSame(200, $this->code());
        self::assertSame([true, 1, 'CARE', 'DUE', $this->today(), 'All programmes'], [$this->json()['careIncluded'], $this->json()['summary']['careDue'], $this->json()['issues'][0]['kind'], $this->json()['issues'][0]['state'], $this->json()['issues'][0]['reviewOn'], $this->json()['issues'][0]['programme']]);
        foreach (['Peanut secret', 'Private care', 'Restricted secret', 'allergies', 'restrictedNotes'] as $secret) {
            self::assertStringNotContainsString($secret, $this->raw());
        }
        self::assertSame($audits, $this->rows('children_audit'), 'dates only: the care payload is not read, so nothing is audited');

        $coordinator = $this->accountWith('coordinator', ['children.safeguarding']);
        $token = $this->login('coordinator');
        self::assertNotSame('', $coordinator);
        $this->get('', $token);
        self::assertSame(200, $this->code());
        self::assertSame([false, 0], [$this->json()['careIncluded'], $this->json()['total']]);
        self::assertStringNotContainsString('"kind":"CARE"', $this->raw());
        $this->get('?kind=CARE', $token);
        self::assertSame(403, $this->code());
    }

    public function testExpiredCarePayloadsAndInactiveEnrolmentsAreExcludedAndBoundsAreValidated(): void
    {
        $this->connection->insert('children_support_records', ['child_id' => $this->child, 'allergies' => 'old', 'care_instructions' => '', 'restricted_notes' => '', 'review_on' => $this->today('-2 days'), 'retain_until' => $this->today('-1 day'), 'version' => 0, 'updated_at' => '2026-01-01 00:00:00+00']);
        $this->get();
        self::assertSame(0, $this->json()['total'], 'past retention');

        $future = $this->person('Future', $this->yearsAgo(9));
        $this->create('/enrolments', ['programmeId' => $this->programme, 'childId' => $future, 'validFrom' => $this->today('+1 day'), 'validUntil' => $this->today('+1 year')]);
        $this->get();
        self::assertSame(0, $this->json()['total'], 'a future enrolment is not active yet');

        foreach (['?days=91', '?days=0', '?kind=SECRET', '?state=LATER', '?limit=0', '?programmeId=nope'] as $query) {
            $this->get($query);
            self::assertSame(400, $this->code(), $query);
        }
    }

    public function testACareReviewIsListedOncePerChildAcrossProgrammes(): void
    {
        $other = $this->create('/programmes', ['name' => 'Midweek children', 'minStaff' => 1, 'childrenPerStaff' => 2]);
        $this->create('/enrolments', ['programmeId' => $other, 'childId' => $this->child, 'validFrom' => $this->today(), 'validUntil' => $this->today('+1 year')]);
        $this->call('PUT', '/api/children/support', $this->admin, ['childId' => $this->child, 'expectedVersion' => 0, 'allergies' => 'Private', 'careInstructions' => '', 'restrictedNotes' => '', 'reviewOn' => $this->today(), 'retainUntil' => $this->today('+1 year')]);

        $this->get('?kind=CARE');

        self::assertSame(200, $this->code());
        self::assertSame([1, 1], [$this->json()['total'], $this->json()['summary']['careDue']]);
        self::assertStringNotContainsString('Private', $this->raw());
    }

    public function testChildrenWhoAreNoLongerChildrenAreLeftOut(): void
    {
        $grown = $this->person('Grown', $this->yearsAgo(17));
        $this->enrol($this->programme, $grown);
        $this->connection->executeStatement('UPDATE people_persons SET date_of_birth = ? WHERE id = ?', [$this->yearsAgo(19), $grown]);

        $this->get('?search=Grown');

        self::assertSame(0, $this->json()['total']);
    }

    public function testASearchThatMatchesNobodyFindsNothing(): void
    {
        $this->get('?search=Nobody%20here');

        self::assertSame(0, $this->json()['total']);
    }

    public function testPermissionsAreEnforced(): void
    {
        $this->call('GET', self::URI);
        self::assertSame(401, $this->code());
        $this->get('', $this->reader);
        self::assertSame(403, $this->code());
        $this->get('?programmeId='.Uuid::v4()->toRfc4122());
        self::assertSame(200, $this->code());
        self::assertSame(0, $this->json()['total']);
    }
}

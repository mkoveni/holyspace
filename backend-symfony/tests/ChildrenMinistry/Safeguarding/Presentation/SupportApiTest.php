<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Safeguarding\Presentation;

use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;

final class SupportApiTest extends ChildrenApiTestCase
{
    private const array SECRETS = ['Peanut allergy', 'Epipen in the red bag', 'Court order forbids contact'];

    private string $child;
    private string $carer;
    private string $manager;
    private string $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->child = $this->person('Child', $this->yearsAgo(8));
        $this->accountWith('carer', ['children.support']);
        $this->carer = $this->login('carer');
        $this->accountWith('manager', ['children.manage', 'children.support']);
        $this->manager = $this->login('manager');
        $this->accountWith('supervisor', ['children.support', 'children.supervise']);
        $this->supervisor = $this->login('supervisor');
    }

    /** @return array<string, mixed> */
    private function body(int $version = 0, ?string $child = null): array
    {
        return ['childId' => $child ?? $this->child, 'expectedVersion' => $version, 'allergies' => self::SECRETS[0], 'careInstructions' => self::SECRETS[1], 'restrictedNotes' => self::SECRETS[2], 'reviewOn' => $this->today('+3 months'), 'retainUntil' => $this->today('+1 year')];
    }

    private function save(int $version = 0): void
    {
        $this->call('PUT', '/api/children/support', $this->manager, $this->body($version));
        self::assertSame(204, $this->code());
    }

    private function expire(string $child, string $retainUntil): void
    {
        $this->connection->insert('children_support_records', ['child_id' => $child, 'allergies' => 'old', 'care_instructions' => '', 'restricted_notes' => '', 'review_on' => $this->today('-1 year'), 'retain_until' => $retainUntil, 'version' => 0, 'updated_at' => '2026-01-01 00:00:00+00']);
    }

    public function testSavingNeedsManageAndSupportAndIsAuditedWithoutThePayload(): void
    {
        foreach ([$this->reader, $this->carer, $this->supervisor] as $token) {
            $this->call('PUT', '/api/children/support', $token, $this->body());
            self::assertSame(403, $this->code());
        }
        $this->call('PUT', '/api/children/support', null, $this->body());
        self::assertSame(401, $this->code());
        self::assertSame(0, $this->rows('children_support_records'));

        $this->save();

        self::assertSame(1, $this->rows('children_audit', "action = 'support.updated' AND target_id = '{$this->child}' AND reason = ''"));
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        $payload = (string) $this->connection->fetchOne("SELECT string_agg(action || reason, ' ') FROM children_audit");
        foreach (self::SECRETS as $secret) {
            self::assertStringNotContainsString($secret, $payload);
        }
    }

    public function testUpdatesNeedTheCurrentVersion(): void
    {
        $this->save();
        $this->save(0);

        $this->call('PUT', '/api/children/support', $this->manager, $this->body(0));
        self::assertSame(409, $this->code());
        $this->call('PUT', '/api/children/support', $this->manager, $this->body(1));
        self::assertSame(204, $this->code());
        $this->call('POST', '/api/children/support/access', $this->manager, ['childId' => $this->child]);
        self::assertSame(2, $this->json()['version']);
        self::assertSame(3, $this->rows('children_audit', "action = 'support.updated'"));
    }

    public function testSaveInputIsChecked(): void
    {
        $adult = $this->person('Adult', $this->yearsAgo(30));
        $bad = [
            'past review' => ['reviewOn' => $this->today('-1 day')] + $this->body(),
            'past retention' => ['retainUntil' => $this->today('-1 day'), 'reviewOn' => $this->today('-2 days')] + $this->body(),
            'retention before review' => ['retainUntil' => $this->today('+1 month')] + $this->body(),
            'malformed date' => ['reviewOn' => 'soon'] + $this->body(),
            'long text' => ['allergies' => str_repeat('x', 2001)] + $this->body(),
            'missing version' => array_diff_key($this->body(), ['expectedVersion' => 1]),
            'not a child' => $this->body(0, $adult),
            'first save with a version' => $this->body(4),
        ];
        foreach ($bad as $name => $body) {
            $this->call('PUT', '/api/children/support', $this->manager, $body);
            self::assertContains($this->code(), [400, 409], $name);
            self::assertNotSame(204, $this->code(), $name);
            $response = (string) $this->client->getResponse()->getContent();
            foreach (self::SECRETS as $secret) {
                self::assertStringNotContainsString($secret, $response, "$name must not echo care details");
            }
        }
        $this->call('PUT', '/api/children/support', $this->manager, ['childId' => '00000000-0000-4000-8000-0000000000aa'] + $this->body());
        self::assertSame(404, $this->code());
        self::assertSame(0, $this->rows('children_support_records'));
        self::assertSame(0, $this->rows('children_child_guards'), 'failed commands leave no trace');
    }

    public function testEveryReadIsAuditedAndNeedsManageWithoutASession(): void
    {
        $this->save();

        foreach ([1, 2] as $reads) {
            $this->call('POST', '/api/children/support/access', $this->manager, ['childId' => $this->child]);
            self::assertSame(200, $this->code());
            self::assertSame(self::SECRETS, [$this->json()['allergies'], $this->json()['careInstructions'], $this->json()['restrictedNotes']]);
            self::assertSame($reads, $this->rows('children_audit', "action = 'support.accessed' AND target_id = '{$this->child}' AND actor_id <> '{$this->adminId}'"));
        }
        $this->call('POST', '/api/children/support/access', $this->carer, ['childId' => $this->child]);
        self::assertSame(403, $this->code(), 'support without manage needs a session');
        $this->call('POST', '/api/children/support/access', $this->admin, ['childId' => $this->child]);
        self::assertSame(200, $this->code(), 'the administrator holds both');
        $this->call('POST', '/api/children/support/access', $this->reader, ['childId' => $this->child]);
        self::assertSame(403, $this->code());
        $this->call('POST', '/api/children/support/access', null, ['childId' => $this->child]);
        self::assertSame(401, $this->code());
        self::assertSame(3, $this->rows('children_audit', "action = 'support.accessed'"), 'refused reads leave no audit row');
    }

    public function testSessionScopedAccessIsDeniedUntilSessionsAreAvailable(): void
    {
        $this->save();

        $this->call('POST', '/api/children/support/access', $this->carer, ['childId' => $this->child, 'sessionId' => '00000000-0000-4000-8000-0000000000aa']);

        self::assertSame(403, $this->code());
        self::assertSame(0, $this->rows('children_audit', "action = 'support.accessed'"));
    }

    public function testMissingExpiredAndMalformedRequestsRevealNothing(): void
    {
        $this->call('POST', '/api/children/support/access', $this->manager, ['childId' => $this->child]);
        self::assertSame(404, $this->code());
        self::assertSame('Current support record was not found.', $this->message());
        $this->expire($this->child, $this->today('-1 day'));
        $this->call('POST', '/api/children/support/access', $this->manager, ['childId' => $this->child]);
        self::assertSame(404, $this->code());
        $this->call('POST', '/api/children/support/access', $this->manager, ['childId' => 'nope']);
        self::assertSame(400, $this->code());
        $this->call('POST', '/api/children/support/access', $this->manager, []);
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('children_audit', "action = 'support.accessed'"));
    }

    public function testPurgeDeletesOnlyExpiredRecordsUnderBothPermissions(): void
    {
        $old = $this->person('Old', $this->yearsAgo(9));
        $edge = $this->person('Edge', $this->yearsAgo(9));
        $this->expire($old, $this->today('-1 day'));
        $this->expire($edge, $this->today());
        $this->save();

        foreach ([$this->carer, $this->manager, $this->reader] as $token) {
            $this->call('POST', '/api/children/support/purge', $token, ['limit' => 10]);
            self::assertSame(403, $this->code());
        }
        foreach ([0, 101] as $limit) {
            $this->call('POST', '/api/children/support/purge', $this->supervisor, ['limit' => $limit]);
            self::assertSame(400, $this->code());
        }
        self::assertSame(3, $this->rows('children_support_records'));

        $this->call('POST', '/api/children/support/purge', $this->supervisor, ['limit' => 10]);

        self::assertSame(200, $this->code());
        self::assertSame('1', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->rows('children_support_records', "child_id = '$old'"));
        self::assertSame(2, $this->rows('children_support_records'), 'retention day itself and current records stay');
        self::assertSame(1, $this->rows('children_audit', "action = 'support.purged' AND target_id = '$old' AND reason = 'Retention expired'"));
    }

    public function testCareDetailsNeverLeaveThroughAnyOtherEndpoint(): void
    {
        $programme = $this->programme();
        $this->enrol($programme, $this->child);
        $this->save();
        $this->call('POST', '/api/children/consent-policies', $this->admin, ['programmeId' => $programme, 'expectedVersion' => 0, 'label' => 'v1', 'documentReference' => 'd', 'contentHash' => str_repeat('a', 64)]);

        foreach (["/api/children/safeguarding/{$this->child}", "/api/children/programmes/$programme/children", "/api/children/programmes/$programme/children/{$this->child}/readiness", "/api/children/enrolments?childId={$this->child}", "/api/people/{$this->child}"] as $path) {
            $this->call('GET', $path, $this->admin);
            $body = (string) $this->client->getResponse()->getContent();
            foreach (self::SECRETS as $secret) {
                self::assertStringNotContainsString($secret, $body, $path);
            }
        }
    }
}

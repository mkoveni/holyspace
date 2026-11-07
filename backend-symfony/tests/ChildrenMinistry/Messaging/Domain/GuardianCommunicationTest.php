<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Messaging\Domain;

use App\ChildrenMinistry\Messaging\Domain\CommunicationStatus;
use App\ChildrenMinistry\Messaging\Domain\GuardianCommunication;
use App\ChildrenMinistry\Messaging\Domain\VerifiedGuardianEmail;
use App\ChildrenMinistry\Messaging\Infrastructure\GuardianTemplatePolicy;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GuardianCommunicationTest extends TestCase
{
    private function draft(string $type = 'CONSENT_RENEWAL', ?string $session = null): GuardianCommunication
    {
        return GuardianCommunication::prepare('id', 'child', 'authority', 'guardian', 'programme', 'policy', $session, $type, 'Hello', CalendarDate::parse('2026-06-10'), 'actor', new DateTimeImmutable('2026-06-01T08:00:00Z'));
    }

    public function testADraftStartsAtVersionZeroWithNoContact(): void
    {
        $draft = $this->draft();

        self::assertSame([CommunicationStatus::Draft, 0, ''], [$draft->status, $draft->version, $draft->contactReference]);
    }

    public function testARemindersNeedsASessionAndAConsentRequestMustNotHaveOne(): void
    {
        foreach ([['SESSION_REMINDER', null], ['CONSENT_RENEWAL', 'session'], ['PHONE', null]] as [$type, $session]) {
            try {
                $this->draft($type, $session);
                self::fail('Expected a rejection.');
            } catch (ChildrenException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame('session', $this->draft('SESSION_REMINDER', 'session')->sessionId);
    }

    public function testFinishingRecordsTheReferenceAndAdvancesTheVersion(): void
    {
        $done = $this->draft()->finish(0, 'CONTACTED', 'Phone call', 'other', new DateTimeImmutable('2026-06-02T08:00:00Z'));

        self::assertSame([CommunicationStatus::Contacted, 1, 'Phone call', 'other', 'actor'], [$done->status, $done->version, $done->contactReference, $done->updatedBy, $done->createdBy]);
    }

    public function testOnlyDraftsFinishAndOnlyToContactedOrCancelledWithAReference(): void
    {
        $now = new DateTimeImmutable('2026-06-02T08:00:00Z');
        $done = $this->draft()->finish(0, 'CANCELLED', 'No longer needed', 'actor', $now);
        foreach ([
            [fn () => $done->finish(1, 'CONTACTED', 'x', 'actor', $now), 'Only draft'],
            [fn () => $this->draft()->finish(0, 'DRAFT', 'x', 'actor', $now), 'Record contact or cancel'],
            [fn () => $this->draft()->finish(0, null, 'x', 'actor', $now), 'Record contact or cancel'],
            [fn () => $this->draft()->finish(0, 'CONTACTED', ' ', 'actor', $now), 'maximum length'],
            [fn () => $this->draft()->finish(4, 'CONTACTED', 'x', 'actor', $now), 'changed'],
        ] as [$attempt, $message]) {
            try {
                $attempt();
                self::fail('Expected a refusal.');
            } catch (ChildrenException $refused) {
                self::assertStringContainsString($message, $refused->getMessage());
            }
        }
    }

    public function testAVerificationIsValidOnlyWithinItsWindowAndUnrevoked(): void
    {
        $from = new DateTimeImmutable('2026-06-01T00:00:00Z');
        $email = new VerifiedGuardianEmail('v', 'g', 'c', 'a@example.org', 'u', $from, $from->modify('+30 days'), 'proof', null);
        $revoked = new VerifiedGuardianEmail('v', 'g', 'c', 'a@example.org', 'u', $from, $from->modify('+30 days'), 'proof', $from->modify('+1 day'));

        self::assertTrue($email->valid($from));
        self::assertFalse($email->valid($from->modify('-1 second')));
        self::assertFalse($email->valid($from->modify('+30 days')), 'the end is exclusive');
        self::assertFalse($revoked->valid($from->modify('+2 days')));
    }

    public function testChildrenTemplatesMustKeepTheMessageAndUseKnownVariables(): void
    {
        $policy = new GuardianTemplatePolicy();

        self::assertNull($policy->violation('children.x', 'S', '<p>{{ message }}</p>', ['message', 'childName']));
        self::assertNull($policy->violation('events.invite', 'S', '<p>No message</p>', ['other']), 'other contexts are not judged');
        self::assertNotNull($policy->violation('children.x', 'S', '<p>No message</p>', ['message']));
        self::assertNotNull($policy->violation('children.x', 'S', '<p>{{message}}</p>', ['message', 'secret']));
    }
}

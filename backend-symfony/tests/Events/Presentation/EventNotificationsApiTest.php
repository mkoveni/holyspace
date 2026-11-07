<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use App\Communications\Application\Contract\DeliveryReader;
use App\Events\Infrastructure\EventNotificationEligibility;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class EventNotificationsApiTest extends EventsApiTestCase
{
    private string $occurrence;
    private string $sender;
    private string $person;
    private string $contact;
    private string $registration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('sender', ['events.communications.send']);
        $this->sender = $this->login('sender');
        $this->event();
        $this->occurrence = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences ORDER BY starts_at LIMIT 1');
        $this->connection->executeStatement("UPDATE event_registration_policies SET open = TRUE WHERE occurrence_id = '{$this->occurrence}'");
        $this->person = $this->person('Ada');
        $this->call('GET', "/api/people/{$this->person}", $this->directory);
        $this->call('POST', "/api/people/{$this->person}/contacts", $this->directory, ['expectedVersion' => (int) $this->json()['person']['version'], 'kind' => 'EMAIL', 'value' => 'ada@example.test', 'primary' => true]);
        self::assertSame(201, $this->code(), $this->message());
        $this->contact = (string) $this->json()['id'];
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/registrations", $this->registrar, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $this->person]);
        $this->registration = (string) $this->json()['id'];
    }

    /** @return array<int|string, mixed> */
    private function review(string $purpose = 'CONFIRMATION', string $message = 'See you there'): array
    {
        $this->call('POST', "/api/events/registrations/{$this->registration}/notification-review", $this->sender, ['contactId' => $this->contact, 'purpose' => $purpose, 'message' => $message]);

        return $this->json();
    }

    /** @return array<string, mixed> */
    private function approval(string $hash, ?string $request = null, string $message = 'See you there', string $purpose = 'CONFIRMATION'): array
    {
        return ['requestId' => $request ?? Uuid::v4()->toRfc4122(), 'contactId' => $this->contact, 'purpose' => $purpose, 'message' => $message, 'reviewHash' => $hash, 'verificationEvidence' => 'Phoned on Sunday'];
    }

    public function testContactsAreTheCurrentEmailAddresses(): void
    {
        $this->call('GET', "/api/events/registrations/{$this->registration}/message-contacts", $this->sender);

        self::assertSame(200, $this->code());
        self::assertSame([['id' => $this->contact, 'value' => 'ada@example.test']], $this->json());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->call('GET', '/api/events/registrations/'.Uuid::v4()->toRfc4122().'/message-contacts', $this->sender);
        self::assertSame(404, $this->code());
    }

    public function testReviewRendersTheNoticeWithoutWritingAnything(): void
    {
        $review = $this->review();

        self::assertSame(200, $this->code(), $this->message());
        self::assertSame('ada@example.test', $review['recipient']);
        self::assertSame('Registration confirmed: Sunday service', $review['subject']);
        self::assertStringContainsString('Hello Ada Mabasa', $review['html']);
        self::assertStringContainsString('See you there', $review['html']);
        self::assertStringContainsString('Main hall', $review['html']);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $review['hash']);
        self::assertSame($review['hash'], $this->review()['hash'], 'a review is deterministic');
        self::assertNotSame($review['hash'], $this->review(message: 'Different words')['hash']);
        self::assertSame(0, $this->rows('event_notifications'));
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testApprovalFreezesTheReviewedContentIntoAQueuedDelivery(): void
    {
        $review = $this->review();
        $request = Uuid::v4()->toRfc4122();

        $this->call('POST', "/api/events/registrations/{$this->registration}/notifications", $this->sender, $this->approval($review['hash'], $request));

        self::assertSame(201, $this->code(), $this->message());
        $delivery = $this->json()['id'];
        $row = $this->connection->fetchAssociative('SELECT * FROM communication_email_deliveries WHERE id = ?', [$delivery]);
        self::assertIsArray($row);
        self::assertSame('QUEUED', $row['status']);
        self::assertSame('ada@example.test', $row['recipient']);
        self::assertSame($review['subject'], $row['subject']);
        self::assertSame('events', $this->connection->fetchOne('SELECT source_context FROM communication_delivery_sources WHERE delivery_id = ?', [$delivery]));
        self::assertSame(1, $this->rows('event_notifications', "id = '$request' AND delivery_id = '$delivery' AND purpose = 'CONFIRMATION' AND registration_version = 0"));
        self::assertSame(1, $this->rows('event_audit', "action = 'notification.approved' AND target_id = '$request'"));

        $this->call('POST', "/api/events/registrations/{$this->registration}/notifications", $this->sender, $this->approval($review['hash'], $request));
        self::assertSame(201, $this->code());
        self::assertSame($delivery, $this->json()['id'], 'a repeat returns the original delivery');
        self::assertSame(1, $this->rows('event_notifications'));
        $this->call('POST', "/api/events/registrations/{$this->registration}/notifications", $this->sender, $this->approval($review['hash'], $request, message: 'Other'));
        self::assertSame(409, $this->code(), 'the same request id with other content');
    }

    public function testApprovalOfAStaleReviewIsRefusedAndRollsBack(): void
    {
        $review = $this->review();
        $this->call('POST', "/api/events/registrations/{$this->registration}/notifications", $this->sender, $this->approval(str_repeat('0', 64)));
        self::assertSame(409, $this->code());

        $this->call('POST', "/api/events/registrations/{$this->registration}/cancel", $this->registrar, ['expectedVersion' => 0, 'reason' => 'Away']);
        $this->call('POST', "/api/events/registrations/{$this->registration}/notifications", $this->sender, $this->approval($review['hash']));
        self::assertSame(409, $this->code(), 'the booking changed after the review');
        self::assertSame(0, $this->rows('event_notifications'));
        self::assertSame(0, $this->rows('communication_email_deliveries'));
    }

    public function testPurposesMatchTheBookingState(): void
    {
        $this->review('CANCELLATION');
        self::assertSame(409, $this->code(), 'a confirmed booking has nothing to cancel');
        $this->review('RESCHEDULED');
        self::assertSame(409, $this->code(), 'an unchanged schedule has nothing to announce');
        $this->review('REMINDER');
        self::assertSame(200, $this->code());
        $this->review('NOPE');
        self::assertSame(400, $this->code());

        $this->call('POST', "/api/events/registrations/{$this->registration}/cancel", $this->registrar, ['expectedVersion' => 0, 'reason' => 'Away']);
        $this->review('CANCELLATION');
        self::assertSame(200, $this->code(), $this->message());
        $this->review();
        self::assertSame(409, $this->code(), 'a cancelled booking cannot be confirmed');
    }

    public function testRescheduledNoticesNeedAScheduleChange(): void
    {
        $this->connection->executeStatement("UPDATE event_occurrences SET schedule_revision = 1 WHERE id = '{$this->occurrence}'");

        $this->review('RESCHEDULED');

        self::assertSame(200, $this->code(), $this->message());
    }

    public function testReviewRejectsBadInput(): void
    {
        foreach ([
            ['contactId' => Uuid::v4()->toRfc4122(), 'purpose' => 'CONFIRMATION', 'message' => 'x'],
            ['contactId' => $this->contact, 'purpose' => 'CONFIRMATION', 'message' => ' '],
            ['contactId' => 'x', 'purpose' => 'CONFIRMATION', 'message' => 'x'],
            ['contactId' => $this->contact, 'message' => 'x'],
        ] as $body) {
            $this->call('POST', "/api/events/registrations/{$this->registration}/notification-review", $this->sender, $body);
            self::assertContains($this->code(), [400, 409], json_encode($body, JSON_THROW_ON_ERROR));
        }
        $this->call('POST', "/api/events/registrations/{$this->registration}/notification-review", $this->sender, ['contactId' => Uuid::v4()->toRfc4122(), 'purpose' => 'CONFIRMATION', 'message' => 'x']);
        self::assertSame(409, $this->code(), 'not one of the person\'s current addresses');
        $this->call('POST', '/api/events/registrations/'.Uuid::v4()->toRfc4122().'/notification-review', $this->sender, ['contactId' => $this->contact, 'purpose' => 'CONFIRMATION', 'message' => 'x']);
        self::assertSame(404, $this->code());
    }

    public function testApprovalRequiresTheEvidenceAndAReviewHash(): void
    {
        $review = $this->review();
        $body = $this->approval($review['hash']);
        foreach ([['verificationEvidence' => ' '], ['reviewHash' => ''], ['purpose' => ''], ['requestId' => 'x'], ['message' => str_repeat('a', 2001)]] as $change) {
            $this->call('POST', "/api/events/registrations/{$this->registration}/notifications", $this->sender, $change + $body);
            self::assertSame(400, $this->code(), json_encode($change, JSON_THROW_ON_ERROR));
        }
        self::assertSame(0, $this->rows('event_notifications'));
    }

    public function testDeliveriesAreListedForTheEventsContextOnly(): void
    {
        $review = $this->review();
        $this->call('POST', "/api/events/registrations/{$this->registration}/notifications", $this->sender, $this->approval($review['hash']));
        $delivery = $this->json()['id'];

        $this->call('GET', '/api/events/deliveries', $this->sender);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame(1, $this->json()['total']);
        self::assertSame($delivery, $this->json()['deliveries'][0]['id']);
        self::assertSame('QUEUED', $this->json()['deliveries'][0]['status']);
        self::assertArrayNotHasKey('recipient', $this->json()['deliveries'][0]);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->call('GET', '/api/events/deliveries?status=FAILED', $this->sender);
        self::assertSame(0, $this->json()['total']);
        foreach (['limit=0', 'limit=101', 'offset=-1'] as $query) {
            $this->call('GET', "/api/events/deliveries?$query", $this->sender);
            self::assertSame(400, $this->code(), $query);
        }
    }

    public function testDispatchRechecksTheBookingAndRecipientBeforeEverySend(): void
    {
        $review = $this->review();
        $this->call('POST', "/api/events/registrations/{$this->registration}/notifications", $this->sender, $this->approval($review['hash']));
        $delivery = $this->json()['id'];
        $reader = self::getContainer()->get(DeliveryReader::class);
        $eligibility = self::getContainer()->get(EventNotificationEligibility::class);
        self::assertInstanceOf(DeliveryReader::class, $reader);
        self::assertInstanceOf(EventNotificationEligibility::class, $eligibility);
        $snapshot = $reader->delivery($delivery);
        self::assertNotNull($snapshot);
        $now = new DateTimeImmutable();

        self::assertTrue($eligibility->eligible($snapshot, $now));

        $this->connection->executeStatement("UPDATE event_registrations SET version = version + 1 WHERE id = '{$this->registration}'");
        self::assertFalse($eligibility->eligible($snapshot, $now), 'the booking changed after approval');
        $this->connection->executeStatement("UPDATE event_registrations SET version = version - 1 WHERE id = '{$this->registration}'");
        self::assertTrue($eligibility->eligible($snapshot, $now));

        $this->connection->executeStatement("UPDATE event_occurrences SET schedule_revision = 1 WHERE id = '{$this->occurrence}'");
        self::assertFalse($eligibility->eligible($snapshot, $now), 'the schedule changed after approval');
        $this->connection->executeStatement("UPDATE event_occurrences SET schedule_revision = 0 WHERE id = '{$this->occurrence}'");

        $this->connection->executeStatement('UPDATE event_definitions SET active = FALSE WHERE id = ?', [$this->connection->fetchOne('SELECT event_id FROM event_occurrences WHERE id = ?', [$this->occurrence])]);
        self::assertFalse($eligibility->eligible($snapshot, $now), 'the event was archived');
    }

    public function testPermissionsAreEnforced(): void
    {
        $routes = [
            ['POST', "/api/events/registrations/{$this->registration}/notifications", $this->approval('x')],
            ['GET', "/api/events/registrations/{$this->registration}/message-contacts", null],
            ['POST', "/api/events/registrations/{$this->registration}/notification-review", ['contactId' => $this->contact, 'purpose' => 'CONFIRMATION', 'message' => 'x']],
            ['GET', '/api/events/deliveries', null],
        ];
        foreach ($routes as [$method, $uri, $body]) {
            $this->call($method, $uri, null, $body);
            self::assertSame(401, $this->code(), $uri);
            $this->call($method, $uri, $this->registrar, $body);
            self::assertSame(403, $this->code(), $uri);
        }
        self::assertSame(0, $this->rows('event_notifications'));
    }
}

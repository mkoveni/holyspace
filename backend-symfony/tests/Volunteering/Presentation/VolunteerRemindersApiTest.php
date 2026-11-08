<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Presentation;

use App\Communications\Application\Contract\DeliveryReader;
use App\Volunteering\Infrastructure\VolunteerReminderEligibility;
use App\Volunteering\Infrastructure\VolunteerTemplatePolicy;
use DateTimeImmutable;

/** Port of Java `VolunteerRemindersApiIntegrationTest`, plus permission, retry and template-policy rules. */
final class VolunteerRemindersApiTest extends VolunteeringApiTestCase
{
    private function contact(): string
    {
        $person = $this->profile()['person']['volunteer']['personId'];
        $this->call('POST', "/api/people/$person/contacts", $this->admin, ['expectedVersion' => 0, 'kind' => 'EMAIL', 'value' => 'solly@example.org', 'primary' => true]);
        self::assertSame(201, $this->code(), $this->body());

        return (string) $this->json()['id'];
    }

    /** @return array<string|int, mixed> */
    private function review(string $assignment, string $contact, string $message): array
    {
        $this->serving('POST', "/assignments/$assignment/reminder-review", ['contactId' => $contact, 'message' => $message]);
        self::assertSame(200, $this->code(), $this->body());

        return $this->json();
    }

    /** @return array<string, mixed> */
    private function approval(string $request, string $contact, string $message, string $hash, string $evidence = 'Volunteer confirmed own email and reminder permission'): array
    {
        return ['requestId' => $request, 'contactId' => $contact, 'message' => $message, 'reviewHash' => $hash, 'verificationEvidence' => $evidence];
    }

    public function testReminderFreezesEscapedPublishedContentAndCancellationInvalidatesDispatch(): void
    {
        $assignment = $this->book($this->volunteer, 'CONFIRMED');
        $contact = $this->contact();
        $message = 'See you <b>soon</b>';
        $preview = $this->review($assignment, $contact, $message);
        self::assertStringContainsString('&lt;b&gt;', $preview['html']);
        self::assertStringNotContainsString('<b>soon', $preview['html']);
        self::assertSame(['assignmentId', 'assignmentVersion', 'shiftRevision', 'contactId', 'recipient', 'subject', 'html', 'hash', 'templateVersion'], array_keys($preview));
        $request = self::uuid();
        $this->serving('POST', "/assignments/$assignment/reminders", $this->approval($request, $contact, $message, $preview['hash']));
        self::assertSame(200, $this->code(), $this->body());
        $deliveryId = $this->connection->fetchOne('SELECT delivery_id FROM serving_reminders WHERE id = ?', [$request]);
        self::assertSame($deliveryId, json_decode($this->body()));
        $this->serving('POST', "/assignments/$assignment/reminders", $this->approval($request, $contact, $message, $preview['hash']));
        self::assertSame($deliveryId, json_decode($this->body()), 'a retried approval returns the original delivery');
        $this->serving('POST', "/assignments/$assignment/reminders", $this->approval($request, $contact, $message, $preview['hash'], 'Different evidence'));
        self::assertSame(409, $this->code());
        self::assertSame('This reminder request was already used.', $this->message());

        $deliveries = self::getContainer()->get(DeliveryReader::class);
        $eligibility = self::getContainer()->get(VolunteerReminderEligibility::class);
        self::assertInstanceOf(DeliveryReader::class, $deliveries);
        self::assertInstanceOf(VolunteerReminderEligibility::class, $eligibility);
        $frozen = $deliveries->delivery((string) $deliveryId);
        self::assertNotNull($frozen);
        self::assertSame($preview['html'], $frozen->body);
        self::assertTrue($eligibility->eligible($frozen, new DateTimeImmutable()));
        $this->serving('POST', "/assignments/$assignment/status", ['expectedVersion' => 0, 'status' => 'CANCELLED', 'reason' => 'Unable to serve']);
        self::assertSame(204, $this->code(), $this->body());
        self::assertFalse($eligibility->eligible($frozen, new DateTimeImmutable()));
        $this->serving('GET', '/deliveries');
        self::assertSame(200, $this->code(), $this->body());
        self::assertSame(1, $this->json()['total']);
        self::assertStringNotContainsString('solly@example.org', $this->body());
        self::assertStringNotContainsString('See you', $this->body());
        self::assertSame(1, $this->rows('serving_audit', "action = 'reminder.approved'"));
    }

    public function testStaleReviewAndBlankVerificationCannotQueueMessages(): void
    {
        $assignment = $this->book($this->volunteer, 'CONFIRMED');
        $contact = $this->contact();
        $preview = $this->review($assignment, $contact, 'Welcome');
        $this->serving('PUT', "/shifts/{$this->shift}", ['expectedVersion' => $this->shiftVersion($this->shift), 'startsAt' => self::at($this->start->modify('+60 seconds')), 'endsAt' => self::at($this->end->modify('+60 seconds')), 'positions' => 2, 'status' => 'OPEN']);
        self::assertSame(204, $this->code(), $this->body());
        $this->serving('POST', "/assignments/$assignment/reminders", $this->approval(self::uuid(), $contact, 'Welcome', $preview['hash'], 'Verified by phone'));
        self::assertSame(409, $this->code());
        $this->serving('POST', "/assignments/$assignment/reminders", $this->approval(self::uuid(), $contact, 'Welcome', $preview['hash'], ' '));
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('serving_reminders'));
    }

    public function testAChangedReviewHashIsRefusedAndContactsAreTheVolunteersOwn(): void
    {
        $assignment = $this->book($this->volunteer, 'CONFIRMED');
        $contact = $this->contact();
        $this->serving('GET', "/assignments/$assignment/reminder-contacts");
        self::assertSame([['id' => $contact, 'value' => 'solly@example.org']], $this->json());
        $this->serving('POST', "/assignments/$assignment/reminders", $this->approval(self::uuid(), $contact, 'Welcome', str_repeat('0', 64)));
        self::assertSame(409, $this->code());
        self::assertSame('The shift, contact or template changed. Review the reminder again.', $this->message());
        $this->serving('POST', "/assignments/$assignment/reminder-review", ['contactId' => self::uuid(), 'message' => 'Welcome']);
        self::assertSame(409, $this->code());
        self::assertSame('Choose a current email address.', $this->message());
        $proposed = $this->book($this->add('/volunteers', ['personId' => $this->person('Naledi')]), 'PROPOSED');
        $this->serving('POST', "/assignments/$proposed/reminder-review", ['contactId' => $contact, 'message' => 'Welcome']);
        self::assertSame(409, $this->code());
        self::assertSame('Reminders require an active volunteer with a confirmed upcoming shift.', $this->message());
    }

    public function testRemindersNeedTheCommunicationsPermission(): void
    {
        $assignment = $this->book($this->volunteer, 'CONFIRMED');
        $reader = $this->readerToken();
        $this->serving('GET', "/assignments/$assignment/reminder-contacts", null, $reader);
        self::assertSame(403, $this->code());
        $this->serving('GET', '/deliveries', null, $reader);
        self::assertSame(403, $this->code());
        $this->call('GET', '/api/volunteering/deliveries');
        self::assertSame(401, $this->code());
        $this->serving('GET', '/deliveries?limit=101');
        self::assertSame(400, $this->code());
    }

    public function testTheReminderTemplateMustKeepItsVariables(): void
    {
        $policy = self::getContainer()->get(VolunteerTemplatePolicy::class);
        self::assertInstanceOf(VolunteerTemplatePolicy::class, $policy);
        $variables = ['name', 'team', 'role', 'startsAt', 'endsAt', 'message'];
        $html = '{{name}} {{team}} {{role}} {{startsAt}} {{endsAt}} {{message}}';
        self::assertNull($policy->violation('volunteering.shift-reminder', 'Shift', $html, $variables));
        self::assertNotNull($policy->violation('volunteering.shift-reminder', 'Shift', '{{name}}', $variables));
        self::assertNull($policy->violation('events.reminder', 'Event', '', []));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use App\Communications\Application\Contract\DeliveryReader;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\Events\Infrastructure\VisitorMessageEligibility;
use DateTimeImmutable;

final class VisitorChildrenAndMessagesApiTest extends EventsApiTestCase
{
    private string $occurrence;
    private string $door;
    private string $welcome;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('door', ['events.attendance.manage', 'events.communications.send']);
        $this->accountWith('welcome', ['events.visitors.read']);
        $this->accountWith('plain', ['events.attendance.manage']);
        $this->door = $this->login('door');
        $this->welcome = $this->login('welcome');
        $this->event($this->draft((new DateTimeImmutable('+2 days'))->format('Y-m-d'), 1));
        $this->occurrence = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences LIMIT 1');
        $this->connection->executeStatement('UPDATE event_registration_policies SET open = TRUE WHERE occurrence_id = ?', [$this->occurrence]);
        $this->connection->executeStatement("UPDATE event_occurrences SET starts_at = now() + interval '1 hour', ends_at = now() + interval '150 minutes' WHERE id = ?", [$this->occurrence]);
    }

    private function link(string $action, ?string $as = null): void
    {
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/visit-link", $as ?? $this->door, ['action' => $action]);
    }

    private function token(): string
    {
        $this->link('ENABLE');
        self::assertSame(200, $this->code(), $this->message());

        return (string) $this->json()['token'];
    }

    /** @param array<string, mixed> $extra */
    private function visit(string $token, array $extra = []): void
    {
        $this->call('POST', "/api/public/visits/$token", null, $extra + [
            'firstName' => 'Thandi', 'lastName' => 'Nkosi', 'email' => 'thandi@example.test', 'consent' => true, 'consentVersion' => '2026-11',
        ]);
    }

    private function dob(string $ago): string
    {
        return (new DateTimeImmutable("-$ago"))->format('Y-m-d');
    }

    public function testChildrenWaitForStaffAndAreNeverEnrolled(): void
    {
        $token = $this->token();
        $this->visit($token, ['children' => [['firstName' => 'Lerato', 'birthDate' => $this->dob('6 years')], ['firstName' => 'Neo', 'birthDate' => $this->dob('2 years')]]]);
        self::assertSame(201, $this->code(), $this->message());
        self::assertSame(2, $this->rows('event_visitor_children', "handover_status = 'PENDING'"));
        self::assertSame(0, $this->rows('children_enrolments'));

        $this->visit($token, ['children' => [['firstName' => 'Extra', 'birthDate' => $this->dob('3 years')]]]);
        self::assertSame(2, $this->rows('event_visitor_children'), 'a repeat submit adds nothing');

        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/visitors", $this->welcome);
        /** @var list<array{id: string, firstName: string, handoverStatus: string}> $children */
        $children = $this->json()['visitors'][0]['children'];
        self::assertSame(['Lerato', 'Neo'], array_column($children, 'firstName'));

        $this->call('POST', "/api/events/visitor-children/{$children[0]['id']}/handover", $this->door, ['decision' => 'HANDED_OVER']);
        self::assertSame(204, $this->code());
        $this->call('POST', "/api/events/visitor-children/{$children[0]['id']}/handover", $this->door, ['decision' => 'HANDED_OVER']);
        self::assertSame(204, $this->code(), 'repeating the same decision is harmless');
        $this->call('POST', "/api/events/visitor-children/{$children[0]['id']}/handover", $this->door, ['decision' => 'DISMISSED']);
        self::assertSame(409, $this->code());
        $this->call('POST', "/api/events/visitor-children/{$children[1]['id']}/handover", $this->welcome, ['decision' => 'DISMISSED']);
        self::assertSame(403, $this->code());
        self::assertSame(1, $this->rows('event_visitor_children', "handover_status = 'HANDED_OVER'"));
    }

    public function testBadChildrenSaveNothing(): void
    {
        $token = $this->token();
        foreach ([
            [['firstName' => 'Future', 'birthDate' => (new DateTimeImmutable('+1 day'))->format('Y-m-d')]],
            [['firstName' => 'Grown', 'birthDate' => $this->dob('30 years')]],
            [['firstName' => 'Odd', 'birthDate' => '2020-02-31']],
            [['firstName' => 'Nodate', 'birthDate' => 'last year']],
            array_fill(0, 9, ['firstName' => 'Many', 'birthDate' => $this->dob('4 years')]),
        ] as $children) {
            $this->visit($token, ['children' => $children]);
            self::assertSame(400, $this->code());
        }
        self::assertSame(0, $this->rows('event_visitor_check_ins'));
        self::assertSame(0, $this->rows('event_visitor_children'));
    }

    public function testRemovedVisitorsChildrenCannotBeHandedOver(): void
    {
        $token = $this->token();
        $this->visit($token, ['children' => [['firstName' => 'Lerato', 'birthDate' => $this->dob('6 years')]]]);
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/visitors", $this->welcome);
        $visitor = $this->json()['visitors'][0];
        $this->call('POST', "/api/events/registrations/{$visitor['registrationId']}/visitor-review", $this->door, ['decision' => 'REMOVE', 'reason' => 'Test', 'expectedAttendanceVersion' => $visitor['attendanceVersion']]);
        self::assertSame(204, $this->code());
        $this->call('POST', "/api/events/visitor-children/{$visitor['children'][0]['id']}/handover", $this->door, ['decision' => 'HANDED_OVER']);
        self::assertSame(409, $this->code());
    }

    public function testWelcomeAndFollowUpAreQueuedOnlyWhenApproved(): void
    {
        $token = $this->token();
        $this->visit($token);
        self::assertSame(0, $this->rows('communication_email_deliveries'), 'messages are off by default');

        $this->link('MESSAGES_ON', $this->login('plain'));
        self::assertSame(403, $this->code(), 'approving needs events.communications.send');
        $this->link('MESSAGES_ON');
        self::assertSame(200, $this->code(), $this->message());
        self::assertTrue($this->json()['messages']);

        $this->visit($token, ['firstName' => 'Sipho', 'lastName' => 'Dlamini', 'email' => 'sipho@example.test']);
        self::assertSame(201, $this->code(), $this->message());
        $this->visit($token, ['firstName' => 'Sipho', 'lastName' => 'Dlamini', 'email' => 'sipho@example.test']);
        self::assertSame(2, $this->rows('communication_email_deliveries', "recipient = 'sipho@example.test'"));
        self::assertSame(1, $this->rows('communication_email_deliveries', "template_key = 'events.visitor_welcome' AND status = 'QUEUED' AND scheduled_at <= now()"));
        self::assertSame(1, $this->rows('communication_email_deliveries', "template_key = 'events.visitor_follow_up' AND scheduled_at > now() + interval '2 hours'"));
        self::assertSame(2, $this->rows('event_visitor_messages'));
        self::assertSame(0, $this->rows('communication_email_deliveries', "recipient = 'thandi@example.test'"));

        $this->link('MESSAGES_OFF');
        self::assertFalse($this->json()['messages']);
        $this->visit($token, ['firstName' => 'Zodwa', 'lastName' => 'Khumalo', 'email' => 'zodwa@example.test']);
        self::assertSame(0, $this->rows('communication_email_deliveries', "recipient = 'zodwa@example.test'"));
    }

    public function testAMobileOnlyVisitorGetsNoEmailAndAMissingTemplateDoesNotBlockCheckIn(): void
    {
        $token = $this->token();
        $this->link('MESSAGES_ON');
        $this->visit($token, ['email' => null, 'mobile' => '0825550101']);
        self::assertSame(201, $this->code(), $this->message());
        self::assertSame(0, $this->rows('communication_email_deliveries'));

        $this->connection->executeStatement("UPDATE communication_templates SET status = 'ARCHIVED' WHERE template_key LIKE 'events.visitor_%'");
        $this->visit($token, ['firstName' => 'Sipho', 'lastName' => 'Dlamini', 'email' => 'sipho@example.test']);
        self::assertSame(201, $this->code(), $this->message());
        self::assertSame(1, $this->rows('event_visitor_check_ins', "full_name = 'Sipho Dlamini'"));
        self::assertSame(0, $this->rows('communication_email_deliveries'));
    }

    public function testEventsStopsAQueuedMessageWhenTheVisitorIsRemovedOrMessagesAreWithdrawn(): void
    {
        $token = $this->token();
        $this->link('MESSAGES_ON');
        $this->visit($token);
        $reader = self::getContainer()->get(DeliveryReader::class);
        $eligibility = self::getContainer()->get(VisitorMessageEligibility::class);
        self::assertInstanceOf(DeliveryReader::class, $reader);
        self::assertInstanceOf(VisitorMessageEligibility::class, $eligibility);
        $id = (string) $this->connection->fetchOne("SELECT id FROM communication_email_deliveries WHERE template_key = 'events.visitor_follow_up'");
        $delivery = $reader->delivery($id);
        self::assertNotNull($delivery);
        self::assertTrue($eligibility->eligible($delivery));

        $this->connection->executeStatement("UPDATE communication_email_deliveries SET recipient = 'someone@else.test' WHERE id = ?", [$id]);
        self::assertFalse($eligibility->eligible($this->snapshot($reader, $id)), 'a changed recipient is refused');
        $this->connection->executeStatement("UPDATE communication_email_deliveries SET recipient = 'thandi@example.test' WHERE id = ?", [$id]);
        self::assertTrue($eligibility->eligible($this->snapshot($reader, $id)));

        $this->connection->executeStatement("UPDATE event_visitor_check_ins SET review_status = 'REMOVED'");
        self::assertFalse($eligibility->eligible($this->snapshot($reader, $id)));
        $this->connection->executeStatement("UPDATE event_visitor_check_ins SET review_status = 'PENDING'");
        $this->link('MESSAGES_OFF');
        self::assertFalse($eligibility->eligible($this->snapshot($reader, $id)));
    }

    public function testStaffMakeAVisitorAMemberAndInviteThemToTheApp(): void
    {
        $this->accountWith('registrar', ['events.attendance.manage', 'events.communications.send', 'people.manage']);
        $this->accountWith('nopeople', ['events.attendance.manage', 'events.communications.send']);
        $registrar = $this->login('registrar');
        $token = $this->token();
        $this->visit($token);
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/visitors", $this->welcome);
        $id = (string) $this->json()['visitors'][0]['registrationId'];
        $person = (string) $this->connection->fetchOne('SELECT person_id FROM event_registrations WHERE id = ?', [$id]);

        $this->call('POST', "/api/events/registrations/$id/visitor-convert", $this->login('nopeople'), []);
        self::assertSame(403, $this->code(), 'making a member needs people.manage');
        $this->call('POST', "/api/events/registrations/$id/visitor-convert", $registrar, ['inviteToApp' => true]);
        self::assertSame(204, $this->code(), $this->message());
        self::assertSame('ACTIVE_MEMBER', $this->connection->fetchOne('SELECT membership_type FROM people WHERE id = ?', [$person]));
        self::assertSame(1, $this->rows('communication_email_deliveries', "template_key = 'events.visitor_app_invite' AND recipient = 'thandi@example.test' AND status = 'QUEUED'"));

        $this->call('POST', "/api/events/registrations/$id/visitor-convert", $registrar, ['inviteToApp' => true]);
        self::assertSame(204, $this->code(), 'repeating is harmless');
        self::assertSame(1, $this->rows('event_visitor_messages', "purpose = 'APP_INVITE'"));

        $reader = self::getContainer()->get(DeliveryReader::class);
        $eligibility = self::getContainer()->get(VisitorMessageEligibility::class);
        self::assertInstanceOf(DeliveryReader::class, $reader);
        self::assertInstanceOf(VisitorMessageEligibility::class, $eligibility);
        $delivery = (string) $this->connection->fetchOne("SELECT id FROM communication_email_deliveries WHERE template_key = 'events.visitor_app_invite'");
        self::assertTrue($eligibility->eligible($this->snapshot($reader, $delivery)));
        $this->connection->executeStatement("UPDATE event_visitor_check_ins SET review_status = 'REMOVED'");
        self::assertFalse($eligibility->eligible($this->snapshot($reader, $delivery)));
    }

    public function testConvertingNeedsAnEmailForAnInvitationAndRefusesRemovedVisitors(): void
    {
        $this->accountWith('registrar', ['events.attendance.manage', 'events.communications.send', 'people.manage']);
        $registrar = $this->login('registrar');
        $token = $this->token();
        $this->visit($token, ['email' => null, 'mobile' => '0825550102']);
        $id = (string) $this->connection->fetchOne('SELECT registration_id FROM event_visitor_check_ins');
        $person = (string) $this->connection->fetchOne('SELECT person_id FROM event_registrations WHERE id = ?', [$id]);

        $this->call('POST', "/api/events/registrations/$id/visitor-convert", $registrar, ['inviteToApp' => true]);
        self::assertSame(400, $this->code());
        self::assertSame('VISITOR', $this->connection->fetchOne('SELECT membership_type FROM people WHERE id = ?', [$person]), 'nothing changed');

        $this->connection->executeStatement("UPDATE event_visitor_check_ins SET review_status = 'REMOVED'");
        $this->call('POST', "/api/events/registrations/$id/visitor-convert", $registrar, []);
        self::assertSame(409, $this->code());
        $this->connection->executeStatement("UPDATE event_visitor_check_ins SET review_status = 'PENDING'");
        $this->call('POST', "/api/events/registrations/$id/visitor-convert", $registrar, []);
        self::assertSame(204, $this->code(), $this->message());
        self::assertSame(0, $this->rows('communication_email_deliveries'));
    }

    private function snapshot(DeliveryReader $reader, string $id): DeliverySnapshot
    {
        return $reader->delivery($id) ?? self::fail('The delivery should exist.');
    }
}

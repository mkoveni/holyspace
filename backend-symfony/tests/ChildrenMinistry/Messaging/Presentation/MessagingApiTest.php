<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Messaging\Presentation;

use App\Communications\Application\DeliveryProcessor;
use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use App\Tests\Support\RecordingEmailTransport;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

/** Guardian messages and their email deliveries against the real schema, with a recording transport. */
final class MessagingApiTest extends ChildrenApiTestCase
{
    private const string COMMS = '/api/children/guardian-communications';
    private const string DELIVERIES = '/api/children/email-deliveries';

    private string $programme;
    private string $group;
    private string $room;
    private string $child;
    private string $guardian;
    private string $authority;
    private string $contact;
    private RecordingEmailTransport $transport;
    private DeliveryProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $transport = self::getContainer()->get(RecordingEmailTransport::class);
        $processor = self::getContainer()->get(DeliveryProcessor::class);
        \assert($transport instanceof RecordingEmailTransport && $processor instanceof DeliveryProcessor);
        $this->transport = $transport;
        $this->processor = $processor;
        $this->transport->reset();
        $this->transport->enabled = true;

        $this->programme = $this->programme();
        $this->group = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $location = $this->create('/locations', ['name' => 'Main', 'timeZone' => 'Africa/Johannesburg']);
        $this->room = $this->create('/rooms', ['locationId' => $location, 'name' => 'Room 1', 'capacity' => 12]);
        $policy = $this->create('/consent-policies', ['programmeId' => $this->programme, 'expectedVersion' => 0, 'label' => 'v1', 'documentReference' => 'docs/v1', 'contentHash' => str_repeat('a', 64)]);
        $this->guardian = $this->person('Guardian', $this->yearsAgo(35));
        $this->child = $this->person('Child', $this->yearsAgo(8));
        $this->authority = $this->create('/guardian-authorities', ['childId' => $this->child, 'guardianId' => $this->guardian, 'validFrom' => $this->at('-1 day'), 'validUntil' => $this->at('+1 year'), 'evidenceReference' => 'private/guardian']);
        $this->create('/consents', ['childId' => $this->child, 'programmeId' => $this->programme, 'policyId' => $policy, 'authorityId' => $this->authority, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'acceptedAt' => $this->at(''), 'evidenceReference' => 'private/consent']);
        $enrolment = $this->enrol($this->programme, $this->child);
        $this->create('/class-assignments', ['enrolmentId' => $enrolment, 'classId' => $this->group, 'validFrom' => $this->today('-1 day'), 'validUntil' => $this->today('+1 month')]);

        $this->call('POST', "/api/people/{$this->guardian}/contacts", $this->admin, ['expectedVersion' => 0, 'kind' => 'EMAIL', 'value' => 'guardian@example.org', 'primary' => true]);
        self::assertSame(201, $this->code(), $this->message());
        $this->contact = (string) $this->connection->fetchOne("SELECT id FROM people_contact_methods WHERE person_id = ? AND kind = 'EMAIL'", [$this->guardian]);
    }

    private function at(string $modify): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('' === $modify ? '+0 seconds' : $modify)->format('Y-m-d\TH:i:s.u\Z');
    }

    /** @return array<string, mixed> */
    private function draft(): array
    {
        return ['requestId' => Uuid::v4()->toRfc4122(), 'childId' => $this->child, 'authorityId' => $this->authority, 'programmeId' => $this->programme, 'type' => 'CONSENT_RENEWAL', 'dueOn' => $this->today()];
    }

    /** @param array<string, mixed> $body */
    private function make(string $path, array $body): string
    {
        $this->call('POST', $path, $this->admin, $body);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function raw(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    /** Prepares a draft, verifies the guardian's address and returns [communication, verification].
     *
     * @return array{string, string}
     */
    private function verified(): array
    {
        $communication = $this->make(self::COMMS, $this->draft());
        $verification = $this->make(self::COMMS."/$communication/verify-email", ['contactId' => $this->contact, 'evidence' => 'Confirmed directly with guardian', 'validUntil' => $this->at('+6 months')]);

        return [$communication, $verification];
    }

    /** @return array<string, mixed> */
    private function queueBody(string $communication, string $verification, ?string $at = null, int $version = 0): array
    {
        $this->call('GET', self::COMMS."/$communication/email-review", $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        $hash = $this->json()['recipients'][0]['reviewHash'];
        self::assertIsString($hash);

        return ['expectedVersion' => $version, 'verificationId' => $verification, 'reviewHash' => $hash, 'scheduledAt' => $at];
    }

    private function queue(string $communication, string $verification): string
    {
        return $this->make(self::COMMS."/$communication/queue-email", $this->queueBody($communication, $verification));
    }

    private function deliveryStatus(string $delivery): string
    {
        return (string) $this->connection->fetchOne('SELECT status FROM communication_email_deliveries WHERE id = ?', [$delivery]);
    }

    private function version(string $delivery): int
    {
        return (int) $this->connection->fetchOne('SELECT version FROM communication_email_deliveries WHERE id = ?', [$delivery]);
    }

    public function testConsentMessagesUseSafeWordingAndContactDoesNotCreateConsentOrDeliveryClaims(): void
    {
        $request = $this->draft();
        $id = $this->make(self::COMMS, $request);
        $this->call('POST', self::COMMS, $this->admin, $request);
        self::assertSame([201, $id], [$this->code(), $this->json()['id']]);
        self::assertSame(1, $this->rows('children_guardian_communications'));

        $this->call('GET', self::COMMS, $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        $list = $this->json();
        self::assertSame([1, 1, 'Child Mabasa', 'Guardian Mabasa', 'DRAFT'], [$list['total'], $list['overdue'], $list['communications'][0]['childName'], $list['communications'][0]['guardianName'], $list['communications'][0]['communication']['status']]);
        self::assertNull($list['communications'][0]['delivery']);
        self::assertStringNotContainsString('message', $this->raw());
        self::assertStringNotContainsString('contactReference', $this->raw());

        $this->call('GET', self::COMMS."/$id", $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        self::assertStringContainsString('please contact the ministry team', $this->json()['message']);
        self::assertStringContainsString('Child Mabasa', $this->json()['message']);
        self::assertSame(['DRAFT', 0, $this->guardian], [$this->json()['status'], $this->json()['version'], $this->json()['guardianId']]);
        foreach (['pickupCode', 'careInstructions', 'evidenceReference'] as $secret) {
            self::assertStringNotContainsString($secret, $this->raw());
        }
        $consents = $this->rows('children_consents');
        $audits = $this->rows('children_audit');
        $this->call('GET', self::COMMS."/$id", $this->admin);
        self::assertSame($audits, $this->rows('children_audit'), 'reading a draft audits nothing');

        $this->call('POST', self::COMMS."/$id/contact", $this->admin, ['expectedVersion' => 0, 'status' => 'CONTACTED', 'reference' => 'Phone conversation confirmed']);
        self::assertSame(204, $this->code());
        self::assertSame($consents, $this->rows('children_consents'), 'contact does not create consent');
        $this->call('GET', self::COMMS.'?status=CONTACTED', $this->admin);
        self::assertSame(1, $this->json()['total']);
        $this->call('POST', self::COMMS."/$id/contact", $this->admin, ['expectedVersion' => 0, 'status' => 'CONTACTED', 'reference' => 'Retry']);
        self::assertSame(409, $this->code());
        $request['dueOn'] = $this->today('+1 day');
        $this->call('POST', self::COMMS, $this->admin, $request);
        self::assertSame(409, $this->code(), 'the same request id with different details');
    }

    public function testAuthorityRevocationAndPolicyChangeInvalidateDraftContactButAllowCancellation(): void
    {
        $id = $this->make(self::COMMS, $this->draft());
        $this->create('/consent-policies', ['programmeId' => $this->programme, 'expectedVersion' => 1, 'label' => 'New policy', 'documentReference' => 'current-policy', 'contentHash' => str_repeat('b', 64)]);

        $this->call('GET', self::COMMS."/$id", $this->admin);
        self::assertSame(409, $this->code());
        $this->call('POST', self::COMMS."/$id/contact", $this->admin, ['expectedVersion' => 0, 'status' => 'CONTACTED', 'reference' => 'Outdated message']);
        self::assertSame(409, $this->code());
        $this->call('POST', self::COMMS."/$id/contact", $this->admin, ['expectedVersion' => 0, 'status' => 'CANCELLED', 'reference' => 'Policy replaced']);
        self::assertSame(204, $this->code());

        $renewed = $this->make(self::COMMS, $this->draft());
        $this->connection->executeStatement('UPDATE children_guardian_authorities SET revoked_at = now() WHERE id = ?', [$this->authority]);
        $this->call('GET', self::COMMS."/$renewed", $this->admin);
        self::assertSame(409, $this->code());
        $this->call('POST', self::COMMS."/$renewed/contact", $this->admin, ['expectedVersion' => 0, 'status' => 'CONTACTED', 'reference' => 'Revoked guardian']);
        self::assertSame(409, $this->code());
        $this->call('POST', self::COMMS."/$renewed/contact", $this->admin, ['expectedVersion' => 0, 'status' => 'CANCELLED', 'reference' => 'Authority revoked']);
        self::assertSame(204, $this->code());
        $this->call('POST', self::COMMS, $this->admin, $this->draft());
        self::assertSame(409, $this->code(), 'a revoked authority cannot start a new message');
    }

    public function testSessionRemindersRequirePlacementAndAFutureSession(): void
    {
        $starts = new DateTimeImmutable($this->today('+7 days').' 09:00', new DateTimeZone('Africa/Johannesburg'));
        $upcoming = $this->create('/sessions', ['classId' => $this->group, 'roomId' => $this->room, 'startsAt' => $starts->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), 'endsAt' => $starts->modify('+1 hour')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), 'seatLimit' => 2]);
        $request = ['type' => 'SESSION_REMINDER', 'sessionId' => $upcoming] + $this->draft();
        $id = $this->make(self::COMMS, $request);

        $this->call('GET', self::COMMS."/$id", $this->admin);
        self::assertStringContainsString('scheduled for', $this->json()['message']);
        self::assertStringContainsString('does not confirm admission', $this->json()['message']);
        self::assertStringContainsString('09:00', $this->json()['message']);

        $other = $this->person('Unplaced', $this->yearsAgo(8));
        $otherAuthority = $this->create('/guardian-authorities', ['childId' => $other, 'guardianId' => $this->guardian, 'validFrom' => $this->at('-1 day'), 'validUntil' => $this->at('+1 year'), 'evidenceReference' => 'Verified']);
        $this->enrol($this->programme, $other);
        $unplaced = ['requestId' => Uuid::v4()->toRfc4122(), 'childId' => $other, 'authorityId' => $otherAuthority] + $request;
        $this->call('POST', self::COMMS, $this->admin, $unplaced);
        self::assertSame(409, $this->code(), 'no class placement covers the session');
        $unplaced['sessionId'] = null;
        $this->call('POST', self::COMMS, $this->admin, ['requestId' => Uuid::v4()->toRfc4122()] + $unplaced);
        self::assertSame(409, $this->code(), 'a reminder needs an upcoming session');
        $consentWithSession = ['requestId' => Uuid::v4()->toRfc4122(), 'type' => 'CONSENT_RENEWAL', 'sessionId' => $upcoming] + $this->draft();
        $this->call('POST', self::COMMS, $this->admin, $consentWithSession);
        self::assertSame(400, $this->code(), 'a consent request has no session');
        $afterSession = ['requestId' => Uuid::v4()->toRfc4122(), 'dueOn' => $this->today('+8 days')] + $request;
        $this->call('POST', self::COMMS, $this->admin, $afterSession);
        self::assertSame(400, $this->code(), 'contact must not follow the session date');
        self::assertSame(1, $this->rows('children_guardian_communications'));
    }

    public function testMalformedRequestsAndMissingPermissionsAreRejected(): void
    {
        foreach ([
            ['type' => 'PHONE_CALL'],
            ['type' => ''],
            ['dueOn' => $this->today('-1 day')],
            ['dueOn' => 'soon'],
            ['requestId' => 'nope'],
            ['childId' => null],
            ['authorityId' => Uuid::v4()->toRfc4122()],
        ] as $change) {
            $this->call('POST', self::COMMS, $this->admin, $change + $this->draft());
            self::assertContains($this->code(), [400, 404], json_encode($change, JSON_THROW_ON_ERROR));
        }
        self::assertSame(0, $this->rows('children_guardian_communications'));
        $this->call('GET', self::COMMS.'?status=SENT', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::COMMS.'/'.Uuid::v4()->toRfc4122(), $this->admin);
        self::assertSame(404, $this->code());
        $this->call('POST', self::COMMS.'/'.Uuid::v4()->toRfc4122().'/contact', $this->admin, ['expectedVersion' => 0, 'status' => 'CONTACTED', 'reference' => 'x']);
        self::assertSame(404, $this->code());

        $id = $this->make(self::COMMS, $this->draft());
        $this->call('POST', self::COMMS."/$id/contact", $this->admin, ['expectedVersion' => 0, 'status' => 'DRAFT', 'reference' => 'x']);
        self::assertSame(400, $this->code());
        $this->call('POST', self::COMMS."/$id/contact", $this->admin, ['expectedVersion' => 0, 'status' => 'CONTACTED']);
        self::assertSame(400, $this->code(), 'a reference is required');
        $this->call('POST', self::COMMS."/$id/contact", $this->admin, ['status' => 'CONTACTED', 'reference' => 'x']);
        self::assertSame(400, $this->code(), 'a missing version is not zero');

        foreach ([['GET', self::COMMS], ['POST', self::COMMS], ['GET', self::COMMS."/$id"], ['GET', self::COMMS."/$id/email-review"], ['POST', self::COMMS."/$id/queue-email"], ['GET', self::DELIVERIES], ['POST', self::DELIVERIES.'/receipts']] as [$method, $uri]) {
            $this->call($method, $uri, null, []);
            self::assertSame(401, $this->code(), "$method $uri");
            $this->call($method, $uri, $this->reader, 'GET' === $method ? null : []);
            self::assertSame(403, $this->code(), "$method $uri as a reader");
        }
    }

    public function testAReviewedQueueIsUniqueAndDispatchSendsOnce(): void
    {
        [$communication, $verification] = $this->verified();
        $body = $this->queueBody($communication, $verification);
        $id = $this->make(self::COMMS."/$communication/queue-email", $body);
        $this->call('POST', self::COMMS."/$communication/queue-email", $this->admin, $body);
        self::assertSame(409, $this->code(), 'one delivery per communication');
        self::assertSame('QUEUED', $this->deliveryStatus($id));

        $this->processor->dispatch($id);
        $this->processor->dispatch($id);

        self::assertCount(1, $this->transport->sent);
        self::assertSame('guardian@example.org', $this->transport->sent[0]->recipient);
        self::assertSame('SENT', $this->deliveryStatus($id));
        $this->call('GET', self::DELIVERIES, $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame(['SENT', 1], [$this->json()['deliveries'][0]['status'], $this->json()['total']]);
        self::assertStringNotContainsString('guardian@example.org', $this->raw());
        self::assertStringNotContainsString('please contact', $this->raw());
        $this->call('GET', self::DELIVERIES."/$id", $this->admin);
        self::assertSame(200, $this->code());
        self::assertSame(['guardian@example.org', 'text/html', 'children.consent-renewal'], [$this->json()['delivery']['recipient'], $this->json()['delivery']['contentType'], $this->json()['delivery']['templateKey']]);
        self::assertContains('SENDING', array_column($this->json()['events'], 'type'));
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->call('POST', self::COMMS."/$communication/contact", $this->admin, ['expectedVersion' => 0, 'status' => 'CONTACTED', 'reference' => 'Manual contact']);
        self::assertSame(409, $this->code(), 'an email delivery exists');
        self::assertSame(1, $this->rows('children_audit', "action = 'guardian.email.queued' AND target_id = '$id'"));
        $this->call('GET', self::COMMS, $this->admin);
        self::assertSame($id, $this->json()['communications'][0]['delivery']['id']);
    }

    public function testNothingIsQueuedWhileEmailIsNotConfigured(): void
    {
        [$communication, $verification] = $this->verified();
        $body = $this->queueBody($communication, $verification);
        $this->transport->enabled = false;

        $this->call('POST', self::COMMS."/$communication/queue-email", $this->admin, $body);

        self::assertSame(409, $this->code());
        self::assertSame(0, $this->rows('communication_email_deliveries'));
    }

    public function testAFutureScheduleDoesNotDispatchAndCancellationIsVersioned(): void
    {
        [$communication, $verification] = $this->verified();
        $id = $this->make(self::COMMS."/$communication/queue-email", $this->queueBody($communication, $verification, $this->at('+1 hour')));

        $this->processor->runDue();
        self::assertSame([[], 'SCHEDULED'], [$this->transport->sent, $this->deliveryStatus($id)]);
        $this->call('POST', self::DELIVERIES."/$id/cancel", $this->admin, ['expectedVersion' => 9, 'reason' => 'Wrong revision']);
        self::assertSame(409, $this->code());
        $this->call('POST', self::DELIVERIES."/$id/cancel", $this->admin, ['expectedVersion' => 0, 'reason' => 'Guardian contacted separately']);
        self::assertSame(204, $this->code());
        $this->processor->runDue();
        self::assertSame([], $this->transport->sent);
        $this->call('POST', self::COMMS."/$communication/contact", $this->admin, ['expectedVersion' => 0, 'status' => 'CONTACTED', 'reference' => 'Telephone confirmed']);
        self::assertSame(204, $this->code(), 'a cancelled delivery no longer blocks manual contact');
    }

    public function testSchedulingIsBoundedToNinetyDays(): void
    {
        [$communication, $verification] = $this->verified();

        foreach (['+91 days', '-10 minutes'] as $when) {
            $this->call('POST', self::COMMS."/$communication/queue-email", $this->admin, $this->queueBody($communication, $verification, $this->at($when)));
            self::assertSame(400, $this->code(), $when);
        }
        $this->call('POST', self::COMMS."/$communication/queue-email", $this->admin, $this->queueBody($communication, $verification, $this->at('+6 months')));
        self::assertSame(400, $this->code(), 'beyond 90 days');
        self::assertSame(0, $this->rows('communication_email_deliveries'));
    }

    public function testARevokedAddressBlocksTheScheduledSend(): void
    {
        [$communication, $verification] = $this->verified();
        $id = $this->queue($communication, $verification);
        $this->call('POST', "/api/children/verified-emails/$verification/revoke", $this->admin, ['reason' => 'Guardian changed address']);
        self::assertSame(204, $this->code());

        $this->processor->dispatch($id);

        self::assertSame('BLOCKED', $this->deliveryStatus($id));
        self::assertSame([], $this->transport->sent);
        $this->call('POST', self::DELIVERIES."/$id/retry", $this->admin, ['expectedVersion' => $this->version($id), 'reason' => 'Try invalid address']);
        self::assertSame(409, $this->code());
    }

    public function testASenderWhoLosesPermissionIsRecheckedByTheDispatcher(): void
    {
        [$communication, $verification] = $this->verified();
        $id = $this->queue($communication, $verification);
        $this->connection->executeStatement("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE code = 'children.communications.send')");

        $this->processor->dispatch($id);

        self::assertSame(['BLOCKED', []], [$this->deliveryStatus($id), $this->transport->sent]);
    }

    public function testEndingThePeopleContactInvalidatesItsVerification(): void
    {
        [$communication, $verification] = $this->verified();
        $id = $this->queue($communication, $verification);
        $this->call('POST', "/api/people/{$this->guardian}/contacts/{$this->contact}/end", $this->admin, ['expectedVersion' => 1]);
        self::assertSame(204, $this->code());

        $this->processor->dispatch($id);

        self::assertSame(['BLOCKED', []], [$this->deliveryStatus($id), $this->transport->sent]);
    }

    public function testAVerificationNeedsTheGuardiansActiveContactAndAShortExpiry(): void
    {
        $communication = $this->make(self::COMMS, $this->draft());
        foreach ([
            ['contactId' => Uuid::v4()->toRfc4122(), 'validUntil' => $this->at('+1 month')],
            ['contactId' => $this->contact, 'validUntil' => $this->at('-1 day')],
            ['contactId' => $this->contact, 'validUntil' => $this->at('+2 years')],
            ['contactId' => $this->contact, 'validUntil' => $this->at('+1 month'), 'evidence' => ''],
            ['contactId' => $this->contact, 'validUntil' => $this->at('+1 month'), 'evidence' => str_repeat('x', 501)],
        ] as $change) {
            $this->call('POST', self::COMMS."/$communication/verify-email", $this->admin, $change + ['evidence' => 'Confirmed']);
            self::assertSame(400, $this->code(), json_encode($change, JSON_THROW_ON_ERROR));
        }
        self::assertSame(0, $this->rows('children_verified_emails'));
        $this->call('POST', self::COMMS.'/'.Uuid::v4()->toRfc4122().'/verify-email', $this->admin, ['contactId' => $this->contact, 'evidence' => 'Confirmed', 'validUntil' => $this->at('+1 month')]);
        self::assertSame(404, $this->code());
    }

    public function testUncertainTransportAndInterruptedWorkersCannotAutomaticallyResend(): void
    {
        [$communication, $verification] = $this->verified();
        $this->transport->outcome = 'UNKNOWN';
        $id = $this->queue($communication, $verification);

        $this->processor->dispatch($id);
        $this->processor->runDue();

        self::assertSame(['UNKNOWN', 1], [$this->deliveryStatus($id), count($this->transport->sent)]);
        $this->call('POST', self::DELIVERIES."/$id/retry", $this->admin, ['expectedVersion' => $this->version($id), 'reason' => 'Unsafe retry']);
        self::assertSame(409, $this->code());
        $this->call('POST', self::DELIVERIES."/$id/cancel", $this->admin, ['expectedVersion' => $this->version($id), 'reason' => 'Unsafe cancellation']);
        self::assertSame(409, $this->code());
    }

    public function testADefiniteFailureCanBeRetriedButTheAttemptLimitIsBounded(): void
    {
        [$communication, $verification] = $this->verified();
        $this->transport->outcome = 'FAILED';
        $id = $this->queue($communication, $verification);
        $this->processor->dispatch($id);

        for ($attempt = 1; $attempt < 3; ++$attempt) {
            $this->call('POST', self::DELIVERIES."/$id/retry", $this->admin, ['expectedVersion' => $this->version($id), 'reason' => 'Configuration corrected']);
            self::assertSame(204, $this->code());
            $this->processor->dispatch($id);
        }

        self::assertCount(3, $this->transport->sent);
        $this->call('POST', self::DELIVERIES."/$id/retry", $this->admin, ['expectedVersion' => $this->version($id), 'reason' => 'Too many attempts']);
        self::assertSame(409, $this->code());
    }

    public function testDeliveredNeedsACorrelatedIdempotentTrustedReceiptAndCaptureCannotClaimDelivery(): void
    {
        [$communication, $verification] = $this->verified();
        $id = $this->queue($communication, $verification);
        $this->processor->dispatch($id);
        $receipt = ['deliveryId' => $id, 'messageId' => $this->transport->sent[0]->messageId, 'eventKey' => 'provider:event-1', 'status' => 'DELIVERED', 'reference' => 'Authenticated provider evidence'];

        $relay = $this->accountWith('relay', ['children.communications.receipts']);
        $relayToken = $this->login('relay');
        $this->call('POST', self::DELIVERIES.'/receipts', $this->admin, $receipt);
        self::assertSame(204, $this->code());
        $this->call('POST', self::DELIVERIES.'/receipts', $this->admin, $receipt);
        self::assertSame(204, $this->code(), 'the same event again is accepted once');
        self::assertSame('DELIVERED', $this->deliveryStatus($id));
        self::assertSame(1, $this->rows('communication_delivery_events', "event_key = 'provider:event-1'"));
        $this->call('POST', self::DELIVERIES.'/receipts', $this->admin, ['status' => 'FAILED'] + $receipt);
        self::assertSame(409, $this->code(), 'a reused key with different content');
        $this->call('POST', self::DELIVERIES.'/receipts', $relayToken, ['eventKey' => 'provider:event-2', 'messageId' => '<wrong@id>'] + $receipt);
        self::assertContains($this->code(), [404, 409]);
        self::assertNotSame('', $relay);
    }

    public function testLocalCaptureIsNeverRepresentedAsRealDelivery(): void
    {
        [$communication, $verification] = $this->verified();
        $this->transport->capture = true;
        $id = $this->queue($communication, $verification);
        $this->processor->dispatch($id);

        $this->call('GET', self::DELIVERIES."/$id", $this->admin);
        self::assertSame([true, 'SENT'], [$this->json()['delivery']['captured'], $this->json()['delivery']['status']]);
        $this->call('POST', self::DELIVERIES.'/receipts', $this->admin, ['deliveryId' => $id, 'messageId' => $this->transport->sent[0]->messageId, 'eventKey' => 'capture-event', 'status' => 'DELIVERED', 'reference' => 'Not a real provider']);
        self::assertSame(409, $this->code());
    }

    public function testAChangedFingerprintOrUnverifiedAddressCannotQueue(): void
    {
        [$communication, $verification] = $this->verified();

        $this->call('POST', self::COMMS."/$communication/queue-email", $this->admin, ['reviewHash' => 'stale'] + $this->queueBody($communication, $verification));
        self::assertSame(409, $this->code());
        $this->call('POST', self::COMMS."/$communication/queue-email", $this->admin, ['verificationId' => Uuid::v4()->toRfc4122()] + $this->queueBody($communication, $verification));
        self::assertSame(409, $this->code());
        $this->call('POST', self::COMMS."/$communication/queue-email", $this->admin, ['expectedVersion' => 7] + $this->queueBody($communication, $verification));
        self::assertSame(409, $this->code());
        $this->call('POST', self::COMMS."/$communication/queue-email", $this->admin, ['reviewHash' => ''] + $this->queueBody($communication, $verification));
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('communication_email_deliveries'));
    }

    public function testTheReviewListsEveryActiveAddressAndOnlyVerifiedOnesCarryAHash(): void
    {
        $communication = $this->make(self::COMMS, $this->draft());

        $this->call('GET', self::COMMS."/$communication/email-review", $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([null, null], [$this->json()['recipients'][0]['verificationId'], $this->json()['recipients'][0]['reviewHash']]);
        self::assertSame(['guardian@example.org', true, 'children.consent-renewal'], [$this->json()['recipients'][0]['email'], $this->json()['transport']['enabled'], $this->json()['templateKey']]);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $verification = $this->make(self::COMMS."/$communication/verify-email", ['contactId' => $this->contact, 'evidence' => 'Confirmed', 'validUntil' => $this->at('+1 month')]);
        $this->call('GET', self::COMMS."/$communication/email-review", $this->admin);
        self::assertSame($verification, $this->json()['recipients'][0]['verificationId']);
        self::assertSame(64, strlen((string) $this->json()['recipients'][0]['reviewHash']));
    }

    public function testAnAbandonedSendingClaimBecomesUncertainWithoutResending(): void
    {
        [$communication, $verification] = $this->verified();
        $id = $this->queue($communication, $verification);
        $this->connection->executeStatement("UPDATE communication_email_deliveries SET status = 'SENDING', started_at = now() - interval '10 minutes', attempt_id = ? WHERE id = ?", [Uuid::v4()->toRfc4122(), $id]);

        $this->processor->runDue();

        self::assertSame(['UNKNOWN', []], [$this->deliveryStatus($id), $this->transport->sent]);
    }

    public function testCancelRetryAndReceiptsOnlyReachChildrenMinistryDeliveries(): void
    {
        $this->call('POST', self::DELIVERIES.'/'.Uuid::v4()->toRfc4122().'/cancel', $this->admin, ['expectedVersion' => 0, 'reason' => 'No such delivery']);
        self::assertSame(404, $this->code());
        $this->call('POST', self::DELIVERIES.'/'.Uuid::v4()->toRfc4122().'/retry', $this->admin, ['expectedVersion' => 0, 'reason' => 'No such delivery']);
        self::assertSame(404, $this->code());
        $this->call('GET', self::DELIVERIES.'/'.Uuid::v4()->toRfc4122(), $this->admin);
        self::assertSame(404, $this->code());
        $this->call('GET', self::DELIVERIES.'?status=MAYBE', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('POST', self::DELIVERIES.'/receipts', $this->admin, ['deliveryId' => Uuid::v4()->toRfc4122(), 'messageId' => 'm', 'eventKey' => 'k', 'status' => 'DELIVERED', 'reference' => 'r']);
        self::assertSame(404, $this->code());
    }
}

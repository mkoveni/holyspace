<?php

declare(strict_types=1);

namespace App\Tests\Payments\Presentation;

use App\Communications\Application\DeliveryProcessor;
use App\Tests\Payments\Support\PaymentApiTestCase;
use App\Tests\Support\RecordingEmailTransport;
use Symfony\Component\Uid\Uuid;

/** Port of Java PaymentOperationsApiIntegrationTest: reviewed donor confirmations through Communications. */
final class PaymentOperationsApiTest extends PaymentApiTestCase
{
    private string $donor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport()->reset();
        $this->transport()->enabled = true;
        $this->transport()->capture = true;
    }

    private function transport(): RecordingEmailTransport
    {
        $transport = self::getContainer()->get(RecordingEmailTransport::class);
        \assert($transport instanceof RecordingEmailTransport);

        return $transport;
    }

    private function processor(): DeliveryProcessor
    {
        $processor = self::getContainer()->get(DeliveryProcessor::class);
        \assert($processor instanceof DeliveryProcessor);

        return $processor;
    }

    private function paidOrder(string $mode = 'live'): string
    {
        $this->scriptGateway($mode);
        $this->donor = $this->person('Receipt');
        $id = $this->order(donor: $this->donor);
        self::assertSame(204, $this->event($id, 'PAID', 'charge_'.substr($id, 0, 8), mode: $mode));

        return $id;
    }

    private function preview(string $order, string $charge, string $contact): string
    {
        $this->call('GET', "/api/payments/$order/receipts?chargeId=$charge&contactId=$contact", $this->admin);
        self::assertSame(200, $this->code(), $this->body());

        return (string) $this->json()['preview']['hash'];
    }

    private function approve(string $order, string $charge, string $contact, string $hash, ?string $request = null): void
    {
        $this->call('POST', "/api/payments/$order/receipts", $this->admin, ['requestId' => $request ?? Uuid::v4()->toRfc4122(), 'chargeId' => $charge,
            'contactId' => $contact, 'reviewHash' => $hash, 'evidence' => 'Donor personally confirmed this address']);
    }

    public function testAChangedContactRequiresANewPreviewBeforeApproval(): void
    {
        $id = $this->paidOrder();
        $charge = $this->chargeOf($id);
        $contact = $this->contact($this->donor, 'receipt@example.org');
        $hash = $this->preview($id, $charge, $contact);
        $this->connection->executeStatement("UPDATE people_contact_methods SET value = 'updated@example.org' WHERE id = ?", [$contact]);
        $this->approve($id, $charge, $contact, $hash);
        self::assertSame(409, $this->code());
        self::assertSame(0, $this->rows('payments_receipts'));
    }

    public function testReviewedConfirmationIsFrozenIdempotentCapturedAndContactChangesBlockDispatch(): void
    {
        $id = $this->paidOrder();
        $charge = $this->chargeOf($id);
        $contact = $this->contact($this->donor, 'receipt@example.org');
        $this->call('GET', "/api/payments/$id/receipts?chargeId=$charge&contactId=$contact", $this->admin);
        self::assertStringContainsString('ZAR 100.00', $this->body());
        self::assertStringContainsString('not a tax certificate', $this->body());
        $hash = (string) $this->json()['preview']['hash'];
        $request = Uuid::v4()->toRfc4122();
        $this->approve($id, $charge, $contact, $hash, $request);
        self::assertSame(201, $this->code(), $this->body());
        $delivery = (string) $this->json()['id'];
        $this->approve($id, $charge, $contact, $hash, $request);
        self::assertSame([201, $delivery], [$this->code(), (string) $this->json()['id']]);
        $this->approve($id, $charge, $contact, $hash);
        self::assertSame(409, $this->code(), 'one receipt per charge');
        self::assertSame(1, $this->rows('payments_receipts'));

        $this->processor()->dispatch($delivery);
        $this->processor()->dispatch($delivery);
        self::assertCount(1, $this->transport()->sent);
        $this->call('GET', "/api/payments/$id/receipts?chargeId=$charge", $this->admin);
        self::assertStringContainsString('"captured":true', $this->body());
        self::assertStringNotContainsString('DELIVERED', $this->body());
    }

    public function testContactChangesBlockAnApprovedConfirmation(): void
    {
        $id = $this->paidOrder();
        $charge = $this->chargeOf($id);
        $contact = $this->contact($this->donor, 'receipt@example.org');
        $this->approve($id, $charge, $contact, $this->preview($id, $charge, $contact));
        $delivery = (string) $this->json()['id'];
        $this->connection->executeStatement("UPDATE people_contact_methods SET value = 'changed@example.org' WHERE id = ?", [$contact]);
        $this->processor()->dispatch($delivery);
        self::assertSame([], $this->transport()->sent);
        $this->call('GET', "/api/payments/$id/receipts?chargeId=$charge", $this->admin);
        self::assertStringContainsString('receipt@example.org', $this->body());
        self::assertStringContainsString('BLOCKED', $this->body());
    }

    public function testSimulationsNeverNeedBankSettlementOrSendDonorConfirmations(): void
    {
        $id = $this->paidOrder('test');
        $this->call('GET', "/api/payments/$id/receipts?chargeId={$this->chargeOf($id)}", $this->admin);
        self::assertSame(409, $this->code());
        self::assertSame(0, $this->rows('giving_gifts'));
    }

    public function testFinancialAccessDoesNotGrantReceiptSendingAndUnrelatedContactsAreRejected(): void
    {
        $id = $this->paidOrder();
        $charge = $this->chargeOf($id);
        $this->call('GET', "/api/payments/$id/receipts?chargeId=$charge&contactId=".Uuid::v4()->toRfc4122(), $this->admin);
        self::assertSame(409, $this->code());
        $this->revoke('payments.receipts.send');
        $this->call('GET', "/api/payments/$id/receipts?chargeId=$charge", $this->admin);
        self::assertSame(403, $this->code());
        self::assertSame(0, $this->rows('payments_receipts'));
    }
}

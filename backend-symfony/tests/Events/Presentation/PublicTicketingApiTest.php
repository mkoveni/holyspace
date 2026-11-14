<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use App\Communications\Application\DeliveryProcessor;
use App\Events\Application\TicketHoldSweeper;
use App\Payments\Application\Contract\GuestAccess;
use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Application\Contract\PaymentProvider;
use App\Tests\Payments\Support\PaymentApiTestCase;
use App\Tests\Support\RecordingEmailTransport;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

/**
 * Public paid events: a visitor with no account buys tickets, pays on Yoco (scripted), and holds tickets the buyer alone can
 * see. The server prices every order; seats are held; money that cannot be honoured is flagged for refund, never lost.
 */
final class PublicTicketingApiTest extends PaymentApiTestCase
{
    private string $occurrence;
    private string $general;
    private string $vip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport()->reset();
        $this->transport()->enabled = true;
        $this->transport()->capture = true;
        $this->scriptGateway('live');
        $this->gateway()->providers = static fn (): array => [new PaymentProvider('yoco', 'Yoco', true, 'live', false)];
        $this->gateway()->checkout = static fn ($order): HostedCheckout => new HostedCheckout('checkout_'.$order->id, 'https://c.yoco.com/checkout/one', []);

        $venue = $this->created('/api/events/venues', ['name' => 'Main hall '.Uuid::v4()->toRfc4122(), 'address' => '1 Church Rd', 'exclusive' => false]);
        $draft = [
            'venueId' => $venue, 'timeZone' => 'Africa/Johannesburg', 'frequency' => 'ONCE', 'interval' => 1, 'count' => 1,
            'firstDate' => (new DateTimeImmutable('+30 days'))->format('Y-m-d'), 'startsAt' => '10:00', 'endsAt' => '11:30', 'endDayOffset' => 0,
        ];
        $this->call('POST', '/api/events/schedule/preview', $this->admin, ['draft' => $draft]);
        $hash = (string) $this->json()['hash'];
        $this->created('/api/events', ['requestId' => Uuid::v4()->toRfc4122(), 'title' => 'Conference', 'type' => 'CONFERENCE', 'description' => 'A day of teaching.', 'schedule' => $draft, 'previewHash' => $hash]);
        $this->occurrence = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences ORDER BY starts_at DESC LIMIT 1');
        $this->general = $this->type('General', 20000);
        $this->vip = $this->type('VIP', 50000);
        $this->openSales();
    }

    private function type(string $name, int $price, ?int $cap = null): string
    {
        return $this->created("/api/events/occurrences/{$this->occurrence}/ticket-types", ['expectedVersion' => 0, 'name' => $name, 'priceMinor' => $price, 'quantityCap' => $cap, 'sortOrder' => (int) ($price / 100)]);
    }

    private function openSales(int $max = 10): void
    {
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/public-sales", $this->admin);
        $version = (int) $this->json()['version'];
        $this->call('PUT', "/api/events/occurrences/{$this->occurrence}/public-sales", $this->admin, ['expectedVersion' => $version, 'enabled' => true, 'maxPerOrder' => $max]);
        self::assertSame(204, $this->code(), $this->body());
    }

    /**
     * @param array<string, int|string> $quantities
     *
     * @return array<string, mixed>
     */
    private function payload(array $quantities, ?string $id = null, string $email = 'buyer@example.org'): array
    {
        return ['requestId' => $id ?? Uuid::v4()->toRfc4122(), 'quantities' => $quantities, 'name' => 'Thandi Mabasa', 'email' => $email];
    }

    /**
     * @param array<string, int>|null $quantities
     *
     * @return array{0: string, 1: string} order id and access token
     */
    private function buy(?array $quantities = null): array
    {
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload($quantities ?? [$this->general => 1]));
        self::assertSame(201, $this->code(), $this->body());

        return [(string) $this->json()['id'], (string) $this->json()['accessToken']];
    }

    private function orderState(string $token): string
    {
        $this->call('POST', '/api/public/ticket-orders/status', null, ['token' => $token]);
        self::assertSame(200, $this->code(), $this->body());

        return (string) $this->json()['state'];
    }

    private function storedStatus(string $orderId): string
    {
        return (string) $this->connection->fetchOne('SELECT status FROM event_ticket_orders WHERE id = ?', [$orderId]);
    }

    public function testTheCatalogShowsTicketTypesAndFeesToTheAnonymousPublicWithoutSeatCounts(): void
    {
        $this->call('GET', '/api/public/events');
        self::assertSame(200, $this->code(), $this->body());
        self::assertSame([$this->occurrence], array_column($this->json()['events'], 'occurrenceId'));
        self::assertSame(20000, $this->json()['events'][0]['fromPriceMinor']);
        $this->call('GET', "/api/public/events/{$this->occurrence}");
        self::assertSame(200, $this->code(), $this->body());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame(['General', 'VIP'], array_column($this->json()['ticketTypes'], 'name'));
        self::assertSame(33925, $this->json()['fees']['effectiveRatePpm']);
        self::assertArrayNotHasKey('capacity', $this->json());
    }

    public function testTheCatalogLinksTheEventsCoverPictureAndNothingElseWhenThereIsNone(): void
    {
        $this->call('GET', '/api/public/events');
        self::assertNull($this->json()['events'][0]['coverImageUrl']);
        self::assertNull($this->json()['events'][0]['bannerImageUrl']);

        $eventId = (string) $this->connection->fetchOne('SELECT event_id FROM event_occurrences WHERE id = ?', [$this->occurrence]);
        $fileId = Uuid::v4()->toRfc4122();
        $this->connection->executeStatement(
            "INSERT INTO media_files (id, owner_type, owner_id, kind, path, mime, width, height, byte_size, sha256, created_at)
             VALUES (?, 'event', ?, 'event', 'events/cover.webp', 'image/webp', 1280, 720, 1000, ?, now())",
            [$fileId, $eventId, str_repeat('a', 64)],
        );
        $this->connection->executeStatement(
            "INSERT INTO media_slots (owner_type, owner_id, kind, file_id, version, updated_at) VALUES ('event', ?, 'event', ?, 1, now())",
            [$eventId, $fileId],
        );

        $this->call('GET', '/api/public/events');
        self::assertSame('/api/public/media/'.$fileId, $this->json()['events'][0]['coverImageUrl']);
        self::assertNull($this->json()['events'][0]['bannerImageUrl']);
        $this->call('GET', "/api/public/events/{$this->occurrence}");
        self::assertSame('/api/public/media/'.$fileId, $this->json()['coverImageUrl']);
    }

    public function testAnEventThatIsNotOnSaleIsNotFound(): void
    {
        $this->call('PUT', "/api/events/occurrences/{$this->occurrence}/public-sales", $this->admin, ['expectedVersion' => 1, 'enabled' => false, 'maxPerOrder' => 10]);
        self::assertSame(204, $this->code(), $this->body());
        $this->call('GET', "/api/public/events/{$this->occurrence}");
        self::assertSame(404, $this->code());
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$this->general => 1]));
        self::assertSame(409, $this->code(), $this->body());
        self::assertSame(0, $this->rows('event_ticket_orders'));
    }

    public function testAVisitorOrdersAndTheServerPricesItWithTheFeeAsItsOwnLine(): void
    {
        $people = $this->rows('people_persons');
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$this->general => 1, $this->vip => 2]));
        self::assertSame(201, $this->code(), $this->body());
        $id = (string) $this->json()['id'];
        self::assertSame($people, $this->rows('people_persons'), 'a buyer never becomes a person');
        self::assertSame('https://c.yoco.com/checkout/one', $this->json()['checkout']['url']);
        $row = $this->connection->fetchAssociative('SELECT * FROM event_ticket_orders WHERE id = ?', [$id]);
        self::assertIsArray($row);
        self::assertSame('RESERVED', $row['status']);
        self::assertSame(120000, (int) $row['subtotal_minor']);
        self::assertSame(120000 + (int) $row['fee_minor'], (int) $row['total_minor']);
        self::assertSame(124214, (int) $row['total_minor']);
        self::assertStringNotContainsString('buyer@example.org', (string) $row['buyer_email_enc']);
        self::assertNotSame($this->json()['accessToken'], $row['access_hash']);
        $payment = $this->connection->fetchAssociative('SELECT purpose, purpose_ref, fund_id, donor_id, amount_minor FROM payments_orders WHERE id = ?', [$id]);
        self::assertSame(['purpose' => 'TICKET', 'purpose_ref' => $id, 'fund_id' => null, 'donor_id' => null, 'amount_minor' => 124214], $payment);
        self::assertSame(2, $this->rows('event_ticket_order_lines', "order_id = '$id'"));
    }

    public function testTheClientCannotChooseThePriceOrUseUnknownTypes(): void
    {
        $body = $this->payload([$this->general => 1]);
        $body['amountMinor'] = 1;
        $body['priceMinor'] = 1;
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $body);
        self::assertSame(201, $this->code(), $this->body());
        self::assertSame(20000, (int) $this->connection->fetchOne('SELECT subtotal_minor FROM event_ticket_orders'));
        foreach ([[], [Uuid::v4()->toRfc4122() => 1], [$this->general => 0], [$this->general => -3], [$this->general => 11], [$this->general => 'many']] as $bad) {
            $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload($bad));
            self::assertContains($this->code(), [400, 422], json_encode($bad).': '.$this->body());
        }
        self::assertSame(1, $this->rows('event_ticket_orders'));
    }

    public function testTheSameRequestReturnsTheSameOrderAndDifferentDetailsConflict(): void
    {
        $request = Uuid::v4()->toRfc4122();
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$this->general => 1], $request));
        $first = $this->json();
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$this->general => 1], $request));
        self::assertSame(201, $this->code(), $this->body());
        self::assertSame([$first['id'], $first['accessToken']], [$this->json()['id'], $this->json()['accessToken']]);
        self::assertSame(1, $this->rows('event_ticket_orders'));
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$this->general => 2], $request));
        self::assertSame(409, $this->code(), $this->body());
    }

    public function testAVerifiedPaymentIssuesTicketsOnceAndTheWalletShowsTheirCodes(): void
    {
        [$id, $token] = $this->buy([$this->general => 2]);
        self::assertSame('AWAITING_PAYMENT', $this->orderState($token));
        $total = (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]);
        self::assertSame(204, $this->event($id, 'PAID', 'charge_t1', $total));
        self::assertSame(204, $this->event($id, 'PAID', 'charge_t1', $total), 'a repeated notification changes nothing');
        self::assertSame('PAID', $this->storedStatus($id));
        self::assertSame(2, $this->rows('event_tickets', "order_id = '$id' AND status = 'ISSUED'"));
        self::assertSame(0, $this->rows('giving_gifts'), 'ticket money never reaches the giving ledger');
        self::assertSame(0, $this->rows('payments_receipts'));

        self::assertSame('PAID', $this->orderState($token));
        $wallet = (string) $this->json()['walletToken'];
        $this->call('POST', '/api/public/ticket-orders/wallet', null, ['token' => $wallet]);
        self::assertSame(200, $this->code(), $this->body());
        $tickets = $this->json()['tickets'];
        self::assertCount(2, $tickets);
        self::assertSame($total, $this->json()['totalMinor']);
        foreach ($tickets as $ticket) {
            [$ticketId, $secret] = explode('.', (string) $ticket['code'], 2);
            self::assertSame($ticket['id'], $ticketId);
            $stored = (string) $this->connection->fetchOne('SELECT code_hash FROM event_tickets WHERE id = ?', [$ticketId]);
            self::assertNotSame($secret, $stored, 'only a hash of the QR secret is stored');
            self::assertSame(hash('sha256', 'guest-access-token:'.$secret), $stored);
        }
    }

    public function testTokensOfOtherOrdersAndGuessesAreNotFound(): void
    {
        [, $token] = $this->buy();
        foreach (['/api/public/ticket-orders/status', '/api/public/ticket-orders/wallet'] as $path) {
            $this->call('POST', $path, null, ['token' => 'wrong-'.$token]);
            self::assertSame(404, $this->code(), $path);
        }
        $this->call('POST', '/api/public/ticket-orders/wallet', null, ['token' => $token]);
        self::assertSame(404, $this->code(), 'the access token is not the wallet token');
    }

    public function testTheLastSeatGoesToOneBuyerOnly(): void
    {
        $this->call('PUT', "/api/events/occurrences/{$this->occurrence}/registration", $this->admin, ['expectedVersion' => 0, 'reason' => 'cap', 'open' => false, 'capacity' => 3, 'waitlistEnabled' => false]);
        self::assertSame(204, $this->code(), $this->body());
        $this->buy([$this->general => 2]);
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$this->general => 2]));
        self::assertSame(409, $this->code(), 'two seats are not left');
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$this->vip => 1]));
        self::assertSame(201, $this->code(), $this->body());
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$this->vip => 1]));
        self::assertSame(409, $this->code(), 'sold out');
    }

    public function testATicketTypeCapIsEnforcedIndependently(): void
    {
        $limited = $this->type('Early bird', 10000, 1);
        $this->buy([$limited => 1]);
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$limited => 1]));
        self::assertSame(409, $this->code(), $this->body());
    }

    public function testALapsedHoldFreesItsSeatsAndAnyLatePaymentIsFlaggedForRefundWithoutTickets(): void
    {
        $this->call('PUT', "/api/events/occurrences/{$this->occurrence}/registration", $this->admin, ['expectedVersion' => 0, 'reason' => 'cap', 'open' => false, 'capacity' => 1, 'waitlistEnabled' => false]);
        [$id] = $this->buy();
        $this->connection->executeStatement("UPDATE event_ticket_orders SET expires_at = now() - interval '1 minute' WHERE id = ?", [$id]);
        $sweeper = self::getContainer()->get(TicketHoldSweeper::class);
        \assert($sweeper instanceof TicketHoldSweeper);
        self::assertSame(1, $sweeper->sweep());
        self::assertSame('EXPIRED', $this->storedStatus($id));
        $this->buy();

        $total = (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]);
        self::assertSame(204, $this->event($id, 'PAID', 'charge_late', $total));
        self::assertSame('LATE_PAID_REVIEW', $this->storedStatus($id));
        self::assertSame(0, $this->rows('event_tickets', "order_id = '$id'"), 'a late payment never confirms a place');
        self::assertNotNull($this->connection->fetchOne('SELECT review_reason FROM payments_orders WHERE id = ?', [$id]));
    }

    public function testAHoldThatIsPastDueIsNotHonouredEvenBeforeTheSweeperRuns(): void
    {
        [$id] = $this->buy();
        $this->connection->executeStatement("UPDATE event_ticket_orders SET expires_at = now() - interval '1 minute' WHERE id = ?", [$id]);
        $total = (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]);
        $this->event($id, 'PAID', 'charge_pastdue', $total);
        self::assertSame('LATE_PAID_REVIEW', $this->storedStatus($id));
    }

    public function testACancelledEventDoesNotIssueTicketsForALaterPayment(): void
    {
        [$id] = $this->buy();
        $this->connection->executeStatement("UPDATE event_occurrences SET status = 'CANCELLED' WHERE id = ?", [$this->occurrence]);
        $total = (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]);
        $this->event($id, 'PAID', 'charge_cancelled', $total);
        self::assertSame('LATE_PAID_REVIEW', $this->storedStatus($id));
        self::assertSame(0, $this->rows('event_tickets'));
    }

    public function testAPaymentOfTheWrongAmountIsNotHonoured(): void
    {
        [$id] = $this->buy();
        $this->event($id, 'PAID', 'charge_short', 100);
        self::assertSame('RESERVED', $this->storedStatus($id), 'the payments layer refuses an amount that is not the order total');
        self::assertSame(0, $this->rows('event_tickets'));
    }

    public function testATestModePaymentReleasesTheHoldAndIssuesNothing(): void
    {
        $this->scriptGateway('test');
        [$id] = $this->buy();
        $total = (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]);
        self::assertSame(204, $this->event($id, 'PAID', 'charge_sandbox', $total, mode: 'test'));
        self::assertSame('RELEASED', $this->storedStatus($id));
        self::assertSame(0, $this->rows('event_tickets'));
    }

    public function testAFullRefundVoidsEveryTicket(): void
    {
        [$id] = $this->buy([$this->general => 2]);
        $total = (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]);
        $this->event($id, 'PAID', 'charge_ref', $total);
        self::assertSame(204, $this->event($id, 'REFUNDED', 'refund_ref', $total));
        self::assertSame('REFUNDED', $this->storedStatus($id));
        self::assertSame(2, $this->rows('event_tickets', "order_id = '$id' AND status = 'VOID'"));
    }

    public function testAPartialRefundKeepsTicketsAndGoesToStaffReview(): void
    {
        [$id] = $this->buy([$this->general => 2]);
        $total = (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]);
        $this->event($id, 'PAID', 'charge_part', $total);
        $this->event($id, 'REFUNDED', 'refund_part', 20000);
        self::assertSame('PAID', $this->storedStatus($id));
        self::assertSame(2, $this->rows('event_tickets', "order_id = '$id' AND status = 'ISSUED'"), 'nothing is guessed');
        self::assertSame(1, $this->rows('payments_history', "order_id = '$id' AND action = 'TICKET_REFUND_NEEDS_SELECTION'"));
    }

    public function testAFreeTicketIsIssuedAtOnceWithoutAPaymentOrder(): void
    {
        $free = $this->type('Child', 0);
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$free => 2]));
        self::assertSame(201, $this->code(), $this->body());
        self::assertNull($this->json()['checkout']);
        $id = (string) $this->json()['id'];
        self::assertSame('PAID', $this->storedStatus($id));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]));
        self::assertSame(2, $this->rows('event_tickets', "order_id = '$id'"));
        self::assertSame(0, $this->rows('payments_orders', "id = '$id'"));
    }

    public function testNoMoreThanTheMaximumPerOrderIsSold(): void
    {
        $this->openSalesWithMax(2);
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$this->general => 3]));
        self::assertSame(400, $this->code(), $this->body());
    }

    private function openSalesWithMax(int $max): void
    {
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/public-sales", $this->admin);
        $this->call('PUT', "/api/events/occurrences/{$this->occurrence}/public-sales", $this->admin, ['expectedVersion' => (int) $this->json()['version'], 'enabled' => true, 'maxPerOrder' => $max]);
        self::assertSame(204, $this->code(), $this->body());
    }

    public function testAFilledHoneypotSavesNothing(): void
    {
        $body = $this->payload([$this->general => 1]);
        $body['website'] = 'http://spam.example';
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $body);
        self::assertSame(201, $this->code());
        self::assertNull($this->json()['accessToken']);
        self::assertSame(0, $this->rows('event_ticket_orders'));
        self::assertSame(0, $this->rows('payments_orders'));
    }

    public function testStaffEndpointsNeedAnAccountAndThePermission(): void
    {
        $this->call('PUT', "/api/events/occurrences/{$this->occurrence}/public-sales", null, ['expectedVersion' => 0, 'enabled' => false]);
        self::assertSame(401, $this->code());
        $this->accountWith('plain', ['events.read']);
        $plain = $this->login('plain');
        foreach ([['PUT', "/api/events/occurrences/{$this->occurrence}/public-sales", ['expectedVersion' => 1, 'enabled' => false]],
            ['POST', "/api/events/occurrences/{$this->occurrence}/ticket-types", ['expectedVersion' => 0, 'name' => 'X', 'priceMinor' => 0]],
            ['GET', "/api/events/occurrences/{$this->occurrence}/ticket-orders", null],
            ['PUT', '/api/ticket-fees', ['expectedVersion' => 0, 'feeRatePpm' => 1, 'feeFixedMinor' => 0, 'providerVatPpm' => 0, 'holdMinutes' => 15]]] as [$method, $path, $body]) {
            $this->call($method, $path, $plain, $body);
            self::assertSame(403, $this->code(), "$method $path");
        }
    }

    public function testStaffSeeBuyersOnlyWithTheContactsPermissionAndStaleVersionsConflict(): void
    {
        $this->buy();
        $this->accountWith('counter', ['events.tickets.read']);
        $counter = $this->login('counter');
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/ticket-orders", $counter);
        self::assertSame(200, $this->code(), $this->body());
        self::assertNull($this->json()['orders'][0]['buyerEmail']);
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/ticket-orders", $this->admin);
        self::assertSame('buyer@example.org', $this->json()['orders'][0]['buyerEmail']);
        $this->call('PUT', "/api/events/occurrences/{$this->occurrence}/ticket-types/{$this->general}", $this->admin, ['expectedVersion' => 7, 'name' => 'General', 'priceMinor' => 21000]);
        self::assertSame(409, $this->code(), $this->body());
        $this->call('PUT', "/api/events/occurrences/{$this->occurrence}/ticket-types/{$this->general}", $this->admin, ['expectedVersion' => 0, 'name' => 'General', 'priceMinor' => 21000]);
        self::assertSame(200, $this->code(), $this->body());
        self::assertSame(20000, (int) $this->connection->fetchOne('SELECT unit_price_minor FROM event_ticket_order_lines LIMIT 1'), 'an edit never changes history');
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

    private function paid(int $seats = 1): string
    {
        [$id] = $this->buy([$this->general => $seats]);
        $total = (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]);
        self::assertSame(204, $this->event($id, 'PAID', 'charge_'.substr($id, 0, 8), $total));

        return $id;
    }

    public function testAPaidOrderQueuesOneTicketsEmailWithAWalletLinkInTheFragment(): void
    {
        $id = $this->paid(2);
        $this->event($id, 'PAID', 'charge_'.substr($id, 0, 8), (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]));
        self::assertSame(1, $this->rows('event_ticket_emails'), 'a repeated notification queues nothing more');
        $delivery = (string) $this->connection->fetchOne('SELECT delivery_id FROM event_ticket_emails');
        $this->processor()->dispatch($delivery);
        self::assertCount(1, $this->transport()->sent);
        $body = (string) $this->connection->fetchOne('SELECT body FROM communication_email_deliveries WHERE id = ?', [$delivery]);
        self::assertSame('buyer@example.org', (string) $this->connection->fetchOne('SELECT recipient FROM communication_email_deliveries WHERE id = ?', [$delivery]));
        self::assertMatchesRegularExpression('~https://church\.example/tickets#[A-Za-z0-9_-]+~', $body);
        self::assertStringContainsString('2 ticket(s)', $body);

        self::assertSame(1, preg_match('~/tickets#([A-Za-z0-9_-]+)~', $body, $m));
        $this->call('POST', '/api/public/ticket-orders/wallet', null, ['token' => $m[1]]);
        self::assertSame(200, $this->code(), $this->body());
        self::assertCount(2, $this->json()['tickets']);
    }

    public function testAFreeOrderEmailsItsTicketsAtOnce(): void
    {
        $free = $this->type('Child', 0);
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$free => 1]));
        self::assertSame(201, $this->code(), $this->body());
        self::assertSame(1, $this->rows('event_ticket_emails'));
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$free => 1], (string) $this->json()['id']));
        self::assertSame(1, $this->rows('event_ticket_emails'), 'a retry queues no second email');
    }

    public function testNoEmailIsQueuedForLateOrSandboxPayments(): void
    {
        [$late] = $this->buy();
        $this->connection->executeStatement("UPDATE event_ticket_orders SET expires_at = now() - interval '1 minute' WHERE id = ?", [$late]);
        $this->event($late, 'PAID', 'charge_l', (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$late]));
        self::assertSame(0, $this->rows('event_ticket_emails'));
        self::assertSame(0, $this->rows('communication_email_deliveries'));
    }

    public function testARefundBeforeSendingBlocksTheFrozenEmail(): void
    {
        $id = $this->paid();
        $delivery = (string) $this->connection->fetchOne('SELECT delivery_id FROM event_ticket_emails');
        $total = (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]);
        $this->event($id, 'REFUNDED', 'refund_x', $total);
        $this->processor()->dispatch($delivery);
        self::assertSame([], $this->transport()->sent);
    }

    /** @return array<mixed> the wallet of a paid order */
    private function wallet(string $orderId): array
    {
        $access = self::getContainer()->get(GuestAccess::class);
        \assert($access instanceof GuestAccess);
        $this->call('POST', '/api/public/ticket-orders/wallet', null, ['token' => $access->token('wallet-'.$orderId)]);
        self::assertSame(200, $this->code(), $this->body());

        return $this->json();
    }

    /** @return list<array<string, mixed>> */
    private function staffTickets(string $orderId): array
    {
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/ticket-orders/$orderId", $this->admin);
        self::assertSame(200, $this->code(), $this->body());

        return $this->json()['tickets'];
    }

    public function testAPaidOrderGetsAGaplessInvoiceWithoutVat(): void
    {
        $first = $this->paid(2);
        $second = $this->paid(1);
        $a = $this->wallet($first)['documents'];
        $b = $this->wallet($second)['documents'];
        self::assertMatchesRegularExpression('/^INV-\d{4}-000001$/', $a['number']);
        self::assertMatchesRegularExpression('/^INV-\d{4}-000002$/', $b['number']);
        self::assertSame('Invoice', $a['title']);
        self::assertSame(0, $a['vatMinor']);
        $total = (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$first]);
        self::assertSame($total, $a['totalMinor']);
        self::assertSame($total, array_sum(array_column($a['lines'], 'lineMinor')), 'the lines add up to the total');
        self::assertSame(1, $this->rows('event_invoices', "ticket_order_id = '$first'"), 'a repeated payment notification issues nothing more');
    }

    public function testAFreeOrderIsNotInvoiced(): void
    {
        $free = $this->type('Child', 0);
        $this->call('POST', "/api/public/events/{$this->occurrence}/orders", null, $this->payload([$free => 1]));
        self::assertSame(0, $this->rows('event_invoices'));
        self::assertNull($this->wallet((string) $this->json()['id'])['documents']);
    }

    public function testInvoicesAreInsertOnly(): void
    {
        $this->paid();
        $this->expectExceptionMessage('immutable');
        $this->connection->executeStatement("UPDATE event_invoices SET buyer_name = 'Someone else'");
    }

    public function testRegisteringForVatChangesOnlyLaterInvoices(): void
    {
        $before = $this->paid();
        $today = (new DateTimeImmutable('now', new DateTimeZone('Africa/Johannesburg')))->format('Y-m-d');
        $this->call('PUT', '/api/invoice-settings', $this->admin, ['effectiveFrom' => $today, 'legalName' => 'Divine Community Space NPC', 'address' => '1 Church Rd', 'registrationNumber' => '2020/1/08', 'npoNumber' => '123-456', 'vatNumber' => '4123456789', 'vatRegistered' => true, 'vatRateBp' => 1500]);
        self::assertSame(204, $this->code(), $this->body());
        $this->call('PUT', '/api/invoice-settings', $this->admin, ['effectiveFrom' => $today, 'legalName' => 'X', 'address' => '', 'registrationNumber' => '', 'npoNumber' => '', 'vatNumber' => '', 'vatRegistered' => false, 'vatRateBp' => 1500]);
        self::assertSame(409, $this->code(), 'the effective date must move forward');
        $after = $this->paid();
        $new = $this->wallet($after)['documents'];
        self::assertSame('Tax Invoice', $new['title']);
        self::assertSame('4123456789', $new['issuer']['vatNumber']);
        self::assertSame(\App\Events\Application\TicketInvoices::vat($new['totalMinor'], 1500), $new['vatMinor']);
        self::assertGreaterThan(0, $new['vatMinor']);
        $old = $this->wallet($before)['documents'];
        self::assertSame('Invoice', $old['title'], 'an invoice already issued never changes');
        self::assertSame(0, $old['vatMinor']);
    }

    public function testAFullRefundIssuesACreditNoteForTheWholeOrder(): void
    {
        $id = $this->paid(2);
        $total = (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]);
        $this->event($id, 'REFUNDED', 'refund_full', $total);
        $notes = $this->wallet($id)['documents']['creditNotes'];
        self::assertCount(1, $notes);
        self::assertMatchesRegularExpression('/^CN-\d{4}-000001$/', $notes[0]['number']);
        self::assertSame($total, $notes[0]['totalMinor']);
        self::assertSame($total, array_sum(array_column($notes[0]['lines'], 'lineMinor')));
        self::assertSame(2, $this->rows('event_tickets', "order_id = '$id' AND credit_note_id IS NOT NULL"));
    }

    public function testAPartialRefundWaitsForStaffToChooseTheTicketsThenIsCredited(): void
    {
        $id = $this->paid(2);
        $this->event($id, 'REFUNDED', 'refund_p1', 20000);
        self::assertSame('PAID', $this->storedStatus($id));
        self::assertSame(20000, (int) $this->connection->fetchOne('SELECT unmatched_refund_minor FROM event_ticket_orders WHERE id = ?', [$id]));

        $ticket = $this->staffTickets($id)[0]['id'];
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/ticket-orders/$id/voids", $this->admin, ['ticketIds' => [$ticket]]);
        self::assertSame(200, $this->code(), $this->body());
        self::assertTrue($this->json()['matched']);
        self::assertSame(0, $this->json()['refundWaitingMinor']);
        self::assertSame('PARTIALLY_REFUNDED', $this->storedStatus($id));
        self::assertSame(1, $this->rows('event_tickets', "order_id = '$id' AND status = 'VOID' AND credit_note_id IS NOT NULL"));
        self::assertSame(1, $this->rows('event_tickets', "order_id = '$id' AND status = 'ISSUED'"));
        $notes = $this->wallet($id)['documents']['creditNotes'];
        self::assertSame([20000], array_column($notes, 'totalMinor'), 'a partial refund returns ticket value only');
    }

    public function testAVoidThatDoesNotMatchTheRefundStaysInReview(): void
    {
        $vip = $this->type('Gold', 50000);
        [$id] = $this->buy([$this->general => 1, $vip => 1]);
        $this->event($id, 'PAID', 'charge_mix', (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]));
        $this->event($id, 'REFUNDED', 'refund_mix', 20000);
        $gold = array_values(array_filter($this->staffTickets($id), static fn (array $t): bool => 'Gold' === $t['type']))[0]['id'];
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/ticket-orders/$id/voids", $this->admin, ['ticketIds' => [$gold]]);
        self::assertFalse($this->json()['matched'], '50000 of tickets do not match a 20000 refund: nothing is guessed');
        self::assertSame(20000, $this->json()['refundWaitingMinor']);
        self::assertSame(0, $this->rows('event_credit_notes'));
        self::assertSame('PAID', $this->storedStatus($id));
    }

    public function testVoidingBeforeTheRefundArrivesIsMatchedWhenItDoes(): void
    {
        $id = $this->paid(2);
        $ticket = $this->staffTickets($id)[1]['id'];
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/ticket-orders/$id/voids", $this->admin, ['ticketIds' => [$ticket]]);
        self::assertFalse($this->json()['matched']);
        self::assertSame(0, $this->rows('event_credit_notes'), 'cancelling in the app never moves money');
        $this->event($id, 'REFUNDED', 'refund_late', 20000);
        self::assertSame('PARTIALLY_REFUNDED', $this->storedStatus($id));
        self::assertSame(1, $this->rows('event_credit_notes'));
    }

    public function testStaffCannotVoidAnotherOrdersTickets(): void
    {
        $a = $this->paid();
        $b = $this->paid();
        $other = $this->staffTickets($b)[0]['id'];
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/ticket-orders/$a/voids", $this->admin, ['ticketIds' => [$other]]);
        self::assertSame(400, $this->code(), $this->body());
        self::assertSame(0, $this->rows('event_tickets', "status = 'VOID'"));
    }

    /** @return array{0: string, 1: string} order id and the QR text of its first ticket */
    private function ticketToScan(int $seats = 1): array
    {
        $id = $this->paid($seats);

        return [$id, (string) $this->wallet($id)['tickets'][0]['code']];
    }

    private function scan(string $code, ?string $token = null, ?string $occurrence = null): void
    {
        $this->call('POST', '/api/events/occurrences/'.($occurrence ?? $this->occurrence).'/tickets/scan', $token ?? $this->admin, ['code' => $code]);
    }

    public function testScanningAdmitsATicketOnceThenReportsItAsUsed(): void
    {
        [, $code] = $this->ticketToScan();
        $this->scan($code);
        self::assertSame(200, $this->code(), $this->body());
        self::assertSame('ADMITTED', $this->json()['result']);
        self::assertSame('General', $this->json()['ticketType']);
        self::assertSame('Thandi Mabasa', $this->json()['holder']);
        self::assertSame(['issued' => 0, 'checkedIn' => 1, 'void' => 0], $this->json()['counts']);
        $this->scan($code);
        self::assertSame('ALREADY_USED', $this->json()['result']);
        self::assertNotNull($this->json()['checkedInAt']);
        self::assertSame(1, $this->rows('event_tickets', "status = 'CHECKED_IN'"));
    }

    public function testScanningRejectsForgedWrongEventAndRefundedTickets(): void
    {
        [$id, $code] = $this->ticketToScan();
        [$ticketId] = explode('.', $code, 2);
        $this->scan($ticketId.'.not-the-secret');
        self::assertSame('REJECTED', $this->json()['result']);
        $this->scan('garbage');
        self::assertSame('REJECTED', $this->json()['result']);
        self::assertSame(0, $this->rows('event_tickets', "status = 'CHECKED_IN'"));

        $this->event($id, 'REFUNDED', 'refund_door', (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]));
        $this->scan($code);
        self::assertSame('REJECTED', $this->json()['result']);
        self::assertStringContainsString('refunded', (string) $this->json()['reason']);
    }

    public function testATicketDoesNotAdmitAtAnotherEvent(): void
    {
        [, $code] = $this->ticketToScan();
        $venue = (string) $this->connection->fetchOne('SELECT id FROM event_venues LIMIT 1');
        $draft = ['venueId' => $venue, 'timeZone' => 'Africa/Johannesburg', 'frequency' => 'ONCE', 'interval' => 1, 'count' => 1, 'firstDate' => (new DateTimeImmutable('+40 days'))->format('Y-m-d'), 'startsAt' => '10:00', 'endsAt' => '11:30', 'endDayOffset' => 0];
        $this->call('POST', '/api/events/schedule/preview', $this->admin, ['draft' => $draft]);
        $this->created('/api/events', ['requestId' => Uuid::v4()->toRfc4122(), 'title' => 'Other', 'type' => 'CONFERENCE', 'description' => '', 'schedule' => $draft, 'previewHash' => (string) $this->json()['hash']]);
        $other = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences ORDER BY starts_at DESC LIMIT 1');
        $this->scan($code, null, $other);
        self::assertSame('REJECTED', $this->json()['result']);
    }

    public function testScanningNeedsTheScanPermission(): void
    {
        [, $code] = $this->ticketToScan();
        $this->scan($code, '');
        self::assertSame(401, $this->code());
        $this->accountWith('reader', ['events.read', 'events.tickets.read']);
        $this->scan($code, $this->login('reader'));
        self::assertSame(403, $this->code());
        self::assertSame(0, $this->rows('event_tickets', "status = 'CHECKED_IN'"));
    }

    public function testStaffCanResendTheTicketsOnceEveryTenMinutes(): void
    {
        $id = $this->paid();
        $url = "/api/events/occurrences/{$this->occurrence}/ticket-orders/$id/resend";
        $this->call('POST', $url, $this->admin);
        self::assertSame(429, $this->code(), 'the first email was queued a moment ago');
        $this->connection->executeStatement("UPDATE event_ticket_emails SET created_at = now() - interval '11 minutes'");
        $this->call('POST', $url, $this->admin);
        self::assertSame(204, $this->code(), $this->body());
        self::assertSame(2, $this->rows('event_ticket_emails'));
        self::assertSame(2, $this->rows('communication_email_deliveries'));
        $newest = (string) $this->connection->fetchOne('SELECT delivery_id FROM event_ticket_emails ORDER BY created_at DESC LIMIT 1');
        $this->processor()->dispatch($newest);
        self::assertCount(1, $this->transport()->sent, 'the resent email passes the delivery checks');
    }

    public function testOnlyAPaidOrderCanBeResent(): void
    {
        [$id] = $this->buy();
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/ticket-orders/$id/resend", $this->admin);
        self::assertSame(409, $this->code());
    }

    public function testABuyerGetsTheSameAnswerWhetherOrNotTheAddressHasTickets(): void
    {
        $this->paid();
        $this->connection->executeStatement("UPDATE event_ticket_emails SET created_at = now() - interval '11 minutes'");
        $this->call('POST', '/api/public/ticket-orders/resend', null, ['email' => 'buyer@example.org']);
        self::assertSame(202, $this->code(), $this->body());
        $known = $this->body();
        self::assertSame(2, $this->rows('event_ticket_emails'));
        $this->call('POST', '/api/public/ticket-orders/resend', null, ['email' => 'nobody@example.org']);
        self::assertSame(202, $this->code());
        self::assertSame($known, $this->body());
        self::assertSame(2, $this->rows('event_ticket_emails'));
        $this->call('POST', '/api/public/ticket-orders/resend', null, ['email' => 'buyer@example.org']);
        self::assertSame(202, $this->code());
        self::assertSame(2, $this->rows('event_ticket_emails'), 'asking again straight away resends nothing');
        $this->call('POST', '/api/public/ticket-orders/resend', null, ['email' => 'not an email']);
        self::assertSame(202, $this->code());
    }

    public function testTheTicketReportCountsSalesRefundsAndDoorEntries(): void
    {
        $this->paid(2);
        $id = $this->paid(1);
        $this->scan((string) $this->wallet($id)['tickets'][0]['code']);
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/ticket-report", $this->admin);
        self::assertSame(200, $this->code(), $this->body());
        $r = $this->json();
        self::assertSame(2, $r['paidOrders']);
        self::assertSame(60000, $r['ticketsMinor']);
        self::assertSame(1, $r['checkedIn']);
        self::assertSame('General', $r['types'][0]['name']);
        self::assertSame(3, $r['types'][0]['sold']);
        self::assertSame(['issued' => 2, 'checkedIn' => 1, 'void' => 0], $r['scan']);
        $this->event($id, 'REFUNDED', 'refund_rep', (int) $this->connection->fetchOne('SELECT total_minor FROM event_ticket_orders WHERE id = ?', [$id]));
        $this->call('GET', "/api/events/occurrences/{$this->occurrence}/ticket-report", $this->admin);
        self::assertGreaterThan(0, $this->json()['refundedMinor']);
        self::assertSame(2, $this->json()['types'][0]['sold']);
    }

    public function testChangingTheRecipientOrTheContentBlocksTheFrozenEmail(): void
    {
        $this->paid();
        $delivery = (string) $this->connection->fetchOne('SELECT delivery_id FROM event_ticket_emails');
        $this->connection->executeStatement('UPDATE communication_email_deliveries SET recipient = ? WHERE id = ?', ['attacker@example.org', $delivery]);
        $this->processor()->dispatch($delivery);
        self::assertSame([], $this->transport()->sent);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use App\Events\Application\TicketHoldSweeper;
use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Application\Contract\PaymentProvider;
use App\Tests\Payments\Support\PaymentApiTestCase;
use DateTimeImmutable;
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
}

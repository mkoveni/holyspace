<?php

declare(strict_types=1);

namespace App\Tests\Payments;

use App\IdentityAccess\Domain\Model\UserAccount;
use App\IdentityAccess\Domain\Repository\UserAccountRepository;
use App\IdentityAccess\Domain\Service\PasswordHasher;
use App\IdentityAccess\Domain\ValueObject\PasswordHash;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\Username;
use App\IdentityAccess\Infrastructure\Security\SymfonyUser;
use App\Payments\Application\Port\PaymentGateway;
use App\Payments\Domain\Model\PaymentOrder;
use App\Payments\Infrastructure\Gateway\HostedPaymentGateway;
use App\Payments\Infrastructure\Gateway\PaymentConfiguration;
use App\People\Application\ReadModel\PersonDirectory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use DomainException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class OnlinePaymentsHttpTest extends WebTestCase
{
    private $client;
    private Connection $db;
    private MockClock $clock;
    private TestPaymentGateway $gateway;
    private TokenStorage $tokens;
    private PaymentAccess $access;
    private string $person;
    private string $other;
    private string $fund;

    protected static function getKernelClass(): string
    {
        return PaymentsHttpKernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient(['debug' => false]);
        $this->client->disableReboot();
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach (['giving', 'payments'] as $context) {
            $this->db->executeStatement('CREATE TABLE '.$context.'_records(id TEXT PRIMARY KEY,kind TEXT,payload TEXT,version INT,external_key TEXT UNIQUE)');
        }
        foreach (['user_accounts(id TEXT PRIMARY KEY,username TEXT)', 'integration_outbox(sequence_no INTEGER PRIMARY KEY AUTOINCREMENT,id TEXT UNIQUE,type TEXT,schema_version INT,payload TEXT,created_at TEXT,processed_at TEXT)', 'payment_notifications(id TEXT PRIMARY KEY,fingerprint TEXT,received_at TEXT)', 'account_security(user_id TEXT PRIMARY KEY,secret TEXT)', 'event_registration_settings(id TEXT PRIMARY KEY,payload TEXT,version INT)', 'event_registrations(id TEXT PRIMARY KEY,occurrence_id TEXT,person_id TEXT,status TEXT,queue_token TEXT,queue_position INT,payload TEXT,version INT,UNIQUE(occurrence_id,person_id))', 'church_events(id TEXT PRIMARY KEY,title TEXT,type TEXT,description TEXT,timezone TEXT,recurrence TEXT,created_at TEXT,updated_at TEXT)', 'church_event_occurrences(id TEXT PRIMARY KEY,event_id TEXT,starts_at TEXT,ends_at TEXT,location TEXT,status TEXT,created_at TEXT,updated_at TEXT)', 'workflow_audit(id TEXT PRIMARY KEY,context TEXT,record_id TEXT,kind TEXT,action TEXT,version INT,actor_id TEXT,occurred_at TEXT,UNIQUE(context,record_id,version))'] as $schema) {
            $this->db->executeStatement('CREATE TABLE '.$schema);
        }
        self::getContainer()->set(Connection::class, $this->db);
        $this->clock = new MockClock('2030-01-01T09:00:00Z');
        Clock::set($this->clock);
        self::getContainer()->set(ClockInterface::class, $this->clock);
        $this->person = Uuid::v7()->toRfc4122();
        $this->other = Uuid::v7()->toRfc4122();
        $directory = $this->createStub(PersonDirectory::class);
        $directory->method('get')->willReturnCallback(fn ($id) => in_array($id, [$this->person, $this->other], true) ? ['id' => $id, 'name' => $id === $this->person ? 'Member One' : 'Member Two', 'email' => 'member@example.test'] : null);
        $directory->method('getMany')->willReturnCallback(static function (array $ids) use ($directory): array {
            $people = [];
            foreach ($ids as $id) {
                $person = $directory->get($id);
                if (null !== $person) {
                    $people[$id] = $person;
                }
            }

            return $people;
        });
        self::getContainer()->set(PersonDirectory::class, $directory);
        self::getContainer()->set(UserAccountRepository::class, $this->createStub(UserAccountRepository::class));
        self::getContainer()->set(PasswordHasher::class, $this->createStub(PasswordHasher::class));
        $user = UserAccount::create(UserId::generate(), Username::fromString('payments.member'), PasswordHash::fromString(password_hash('Fixture-Test-123!', PASSWORD_BCRYPT)), $this->person);
        $this->tokens = new TokenStorage();
        $token = new UsernamePasswordToken(new SymfonyUser($user), 'test', ['ROLE_USER']);
        $token->setAttribute('mfa_verified', true);
        $this->tokens->setToken($token);
        self::getContainer()->set(TokenStorageInterface::class, $this->tokens);
        $this->db->insert('account_security', ['user_id' => $user->id()->toString(), 'secret' => 'fixture-secret']);
        $this->access = new PaymentAccess();
        self::getContainer()->set(AuthorizationCheckerInterface::class, $this->access);
        $this->gateway = new TestPaymentGateway($this->db);
        self::getContainer()->set(PaymentGateway::class, $this->gateway);
        $this->fund = self::getContainer()->get(\App\Giving\Application\Command\CreateFund\CreateFundHandler::class)(new \App\Giving\Application\Command\CreateFund\CreateFundCommand('General giving'))->id;
        $this->request('PUT', '/api/payments/funds', ['fundIds' => [$this->fund], 'expectedVersion' => 0]);
    }

    private function request(string $method, string $path, array $payload = [], int $status = 200): array
    {
        $this->client->jsonRequest($method, $path, $payload);
        self::assertResponseStatusCodeSame($status);

        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    private function checkout(string $provider = 'yoco', bool $recurring = false, string $key = 'one', int $status = 201): array
    {
        return $this->request('POST', '/api/payments/checkout', ['provider' => $provider, 'purpose' => 'giving', 'targetId' => $this->fund, 'amountMinor' => 12345, 'recurring' => $recurring, 'requestKey' => $key], $status);
    }

    private function event(array $order, string $id = 'charge-1', string $type = 'paid', ?int $amount = null, ?string $token = null): array
    {
        return ['provider' => $order['provider'], 'mode' => $order['mode'], 'eventId' => $id.':'.$type, 'reference' => $id, 'type' => $type, 'checkoutId' => 'yoco' === $order['provider'] ? 'checkout_'.$order['id'] : null, 'orderId' => $order['id'], 'amountMinor' => $amount ?? $order['amountMinor'], 'currency' => 'ZAR', 'token' => $token];
    }

    private function notify(array $event, int $status = 200): void
    {
        $this->request('POST', '/api/payments/webhooks/'.$event['provider'], $event, $status);
    }

    private function gifts(): array
    {
        return self::getContainer()->get(\App\Giving\Application\Query\GetGivingOverview\GetGivingOverviewHandler::class)(new \App\Giving\Application\Query\GetGivingOverview\GetGivingOverviewQuery())['gifts'];
    }

    private function getOrder(string $id): array
    {
        return $this->request('GET', '/api/payments/member/'.$id);
    }

    private function occurrence(): array
    {
        $event = $this->request('POST', '/api/events', ['title' => 'Church dinner', 'type' => 'other', 'startsAt' => '2030-01-02T09:00', 'endsAt' => '2030-01-02T12:00', 'location' => 'Hall', 'timezone' => 'UTC'], 201);
        $occurrence = $this->request('GET', '/api/events/'.$event['id'])['occurrences'][0];
        $base = '/api/events/'.$event['id'].'/occurrences/'.$occurrence['id'].'/registrations';
        $settings = $this->request('PUT', $base.'/settings', ['open' => true, 'capacity' => 1, 'waitlistEnabled' => true, 'expectedVersion' => 0]);
        $this->request('PUT', $base.'/ticket-price', ['amountMinor' => 5000, 'expectedVersion' => $settings['version']]);

        return $occurrence + ['base' => $base];
    }

    private function ticket(array $occurrence, string $key = 'ticket', int $status = 201): array
    {
        return $this->request('POST', '/api/payments/checkout', ['provider' => 'yoco', 'purpose' => 'ticket', 'targetId' => $occurrence['id'], 'requestKey' => $key], $status);
    }

    public function testVerifiedOneOffPaymentsAreIdempotentAndDoNotRegressAfterFailure(): void
    {
        $order = $this->checkout();
        $same = $this->checkout();
        self::assertSame($order['id'], $same['id']);
        self::assertSame(1, $this->gateway->calls);
        $event = $this->event($order);
        $this->notify($event);
        $this->notify($event);
        $event['eventId'] = 'retry-different-notification';
        $this->notify($event);
        self::assertNotEmpty($this->request('GET', '/api/workflows/payments/'.$order['id'].'/audit')['entries']);
        self::assertCount(1, $this->gifts());
        self::assertSame($this->person, $this->gifts()[0]->donorId);
        self::assertSame('online', $this->gifts()[0]->method);
        $this->notify($this->event($order, 'failed-old', 'failed'));
        self::assertSame('paid', $this->getOrder($order['id'])['status']);
        $this->checkout(key: 'one', status: 409);
    }

    public function testMismatchedCurrencyOrAmountCannotPostMoneyOrTickets(): void
    {
        $order = $this->checkout();
        $this->notify($this->event($order, amount: 12346));
        self::assertCount(0, $this->gifts());
        self::assertSame('review', $this->getOrder($order['id'])['status']);
        $other = $this->checkout(key: 'two');
        $event = $this->event($other, 'usd');
        $event['currency'] = 'USD';
        $this->notify($event);
        self::assertCount(0, $this->gifts());
        $this->request('POST', '/api/payments/checkout', ['provider' => 'yoco', 'purpose' => 'giving', 'targetId' => $this->fund, 'amountMinor' => 12345, 'requestKey' => 'invalid-currency', 'currency' => 'USD'], 422);
    }

    public function testAccountOwnershipMfaAndStaffPermissionsApply(): void
    {
        $order = $this->checkout();
        $this->access->codes = ['member.portal'];
        $this->request('GET', '/api/payments', [], 403);
        $this->request('PUT', '/api/payments/funds', ['fundIds' => [], 'expectedVersion' => 1], 403);
        $other = UserAccount::create(UserId::generate(), Username::fromString('other.member'), PasswordHash::fromString('fixture-hash'), $this->other);
        $token = new UsernamePasswordToken(new SymfonyUser($other), 'test', ['ROLE_USER']);
        $token->setAttribute('mfa_verified', true);
        $this->tokens->setToken($token);
        $this->db->insert('account_security', ['user_id' => $other->id()->toString(), 'secret' => 'fixture']);
        $this->request('GET', '/api/payments/member/'.$order['id'], [], 404);
        self::assertSame([], $this->request('GET', '/api/payments/member')['orders']);
        $token->setAttribute('mfa_verified', false);
        $this->request('GET', '/api/payments/member', [], 403);
        $this->checkout(key: 'not-verified', status: 403);
    }

    public function testAmbiguousCheckoutRetriesReuseTheOriginalOrder(): void
    {
        $this->gateway->failCheckout = true;
        $this->checkout(status: 503);
        self::assertSame(1, (int) $this->db->fetchOne("SELECT COUNT(*) FROM payments_records WHERE kind='orders'"));
        $this->gateway->failCheckout = false;
        $order = $this->checkout();
        self::assertSame(2, $this->gateway->calls);
        self::assertSame($this->gateway->ids[0], $this->gateway->ids[1]);
        self::assertSame('ready', $order['status']);
    }

    public function testRecurringChargesUseOneTokenAndCancellationRemainsClosedForLatePayments(): void
    {
        $order = $this->checkout('payfast', true);
        $token = Uuid::v4()->toRfc4122();
        $this->notify($this->event($order, token: $token));
        $this->notify($this->event($order, 'month-2', token: $token));
        self::assertCount(2, $this->gifts());
        $current = $this->getOrder($order['id']);
        self::assertArrayNotHasKey('encryptedToken', $current);
        self::assertStringNotContainsString($token, json_encode($current));
        $this->checkout('payfast', true, 'duplicate-subscription', 409);
        $this->gateway->failCancel = true;
        $this->request('POST', '/api/payments/member/'.$order['id'].'/cancel-subscription', ['expectedVersion' => $current['version']], 503);
        $current = $this->getOrder($order['id']);
        self::assertSame('cancel_requested', $current['subscriptionStatus']);
        $this->gateway->failCancel = false;
        $current = $this->request('POST', '/api/payments/member/'.$order['id'].'/cancel-subscription', ['expectedVersion' => $current['version']]);
        self::assertSame('cancelled', $current['subscriptionStatus']);
        $this->notify($this->event($order, 'in-flight-month', token: $token));
        self::assertCount(3, $this->gifts());
        self::assertSame('cancelled', $this->getOrder($order['id'])['subscriptionStatus']);
    }

    public function testYocoCannotCreateRecurringGiving(): void
    {
        $this->checkout('yoco', true, status: 422);
        self::assertSame(0, $this->gateway->calls);
    }

    public function testTicketReservationsBlockOversellingAndLatePaymentsDoNotConfirmASeat(): void
    {
        $occurrence = $this->occurrence();
        $order = $this->ticket($occurrence);
        $roster = $this->request('GET', $occurrence['base']);
        self::assertSame(0, $roster['remaining']);
        self::assertSame(1, $roster['counts']['pending_payment']);
        $this->request('POST', $occurrence['base'], ['personId' => $this->other], 409);
        $this->request('PUT', $occurrence['base'].'/ticket-price', ['amountMinor' => 6000, 'expectedVersion' => $roster['settings']['version']], 409);
        self::getContainer()->get(\App\Payments\Application\Command\StartPayment\StartPaymentHandler::class)(new \App\Payments\Application\Command\StartPayment\StartPaymentCommand($this->other, 'yoco', 'giving', $this->fund, 1000, false, 'other-giving'));
        $calls = $this->gateway->calls;
        try {
            self::getContainer()->get(\App\Payments\Application\Command\StartPayment\StartPaymentHandler::class)(new \App\Payments\Application\Command\StartPayment\StartPaymentCommand($this->other, 'yoco', 'ticket', $occurrence['id'], null, false, 'second-ticket'));
            self::fail('A full event must reject checkout.');
        } catch (DomainException) {
        }
        self::assertSame($calls, $this->gateway->calls);
        $this->clock->sleep(16 * 60);
        self::getContainer()->get(\App\Payments\Application\Command\MaintainPayments\MaintainPaymentsHandler::class)(new \App\Payments\Application\Command\MaintainPayments\MaintainPaymentsCommand());
        $roster = $this->request('GET', $occurrence['base']);
        self::assertSame(1, $roster['remaining']);
        self::getContainer()->get(\App\Payments\Application\Command\StartPayment\StartPaymentHandler::class)(new \App\Payments\Application\Command\StartPayment\StartPaymentCommand($this->other, 'yoco', 'ticket', $occurrence['id'], null, false, 'second-ticket'));
        $this->notify($this->event($order));
        self::assertSame('review', $this->getOrder($order['id'])['status']);
        self::assertSame(0, $this->request('GET', $occurrence['base'])['counts']['confirmed']);
        self::assertCount(0, $this->gifts());
    }

    public function testPaidTicketIsSeparateFromGivingAndRefundReleasesItsPlace(): void
    {
        $occurrence = $this->occurrence();
        $order = $this->ticket($occurrence);
        $this->notify($this->event($order));
        $roster = $this->request('GET', $occurrence['base']);
        self::assertSame(1, $roster['counts']['confirmed']);
        self::assertCount(0, $this->gifts());
        $charge = $this->getOrder($order['id'])['charges'][0];
        $this->request('POST', '/api/payments/charges/'.$charge['id'].'/refund', ['amountMinor' => 5000, 'reference' => 'provider-refund-1', 'confirmed' => true, 'expectedVersion' => $charge['version']]);
        self::assertSame(1, $this->request('GET', $occurrence['base'])['remaining']);
    }

    public function testPartialAndFullRefundsUpdateStatementsAndInvalidatePledgeCredits(): void
    {
        $order = $this->checkout();
        $this->notify($this->event($order));
        $gift = $this->gifts()[0];
        $receipt = self::getContainer()->get(\App\Giving\Application\Command\IssueReceipt\IssueReceiptHandler::class)(new \App\Giving\Application\Command\IssueReceipt\IssueReceiptCommand($gift->id));
        $pledge = self::getContainer()->get(\App\Giving\Application\Command\CreatePledge\CreatePledgeHandler::class)(new \App\Giving\Application\Command\CreatePledge\CreatePledgeCommand(['donorId' => $this->person, 'fundId' => $this->fund, 'amountMinor' => 12345, 'currency' => 'ZAR', 'dueOn' => '2030-12-31']));
        self::getContainer()->get(\App\Giving\Application\Command\AllocatePledge\AllocatePledgeHandler::class)(new \App\Giving\Application\Command\AllocatePledge\AllocatePledgeCommand($pledge->id, $gift->id, 12345, 'assignment'));
        $partial = $this->event($order, 'refund-1', 'refunded', 2345);
        $this->notify($partial);
        $partial['eventId'] = 'refund-repeat-new-event';
        $this->notify($partial);
        $giving = self::getContainer()->get(\App\Giving\Application\Query\GetGivingOverview\GetGivingOverviewHandler::class)(new \App\Giving\Application\Query\GetGivingOverview\GetGivingOverviewQuery());
        self::assertCount(1, $giving['reversals']);
        self::assertTrue($giving['receipts'][0]->void);
        self::assertSame('posted', $this->gifts()[0]->status);
        self::assertSame(10000, $giving['totals'][0]['netAmountMinor']);
        self::assertSame('cancelled', self::getContainer()->get(\App\Giving\Application\Query\GetPledgeProgress\GetPledgeProgressHandler::class)(new \App\Giving\Application\Query\GetPledgeProgress\GetPledgeProgressQuery('2030-01-01'))['allocations'][0]->status);
        $this->notify($this->event($order, 'refund-2', 'refunded', 10000));
        self::assertSame('reversed', $this->gifts()[0]->status);
        self::assertSame(0, self::getContainer()->get(\App\Giving\Application\Query\GetGivingOverview\GetGivingOverviewHandler::class)(new \App\Giving\Application\Query\GetGivingOverview\GetGivingOverviewQuery())['totals'][0]['netAmountMinor']);
        self::assertSame(12345, $this->getOrder($order['id'])['charges'][0]['refundedMinor']);
    }

    public function testRefundArrivingBeforeItsPaymentIsRetainedAndAppliedLater(): void
    {
        $order = $this->checkout();
        $this->notify($this->event($order, 'refund-before-charge', 'refunded'));
        self::assertSame(1, (int) $this->db->fetchOne("SELECT COUNT(*) FROM payments_records WHERE kind='refunds'"));
        $this->notify($this->event($order));
        self::assertSame('reversed', $this->gifts()[0]->status);
        self::assertSame(12345, $this->getOrder($order['id'])['charges'][0]['refundedMinor']);
    }

    public function testSandboxPaymentsNeverPostRealGiftsOrConfirmTickets(): void
    {
        $this->gateway->environment = 'test';
        $order = $this->checkout();
        $this->notify($this->event($order));
        self::assertCount(0, $this->gifts());
        $occurrence = $this->occurrence();
        $ticket = $this->ticket($occurrence);
        $this->notify($this->event($ticket, 'sandbox-ticket'));
        self::assertSame(0, $this->request('GET', $occurrence['base'])['counts']['confirmed']);
        self::assertSame(1, $this->request('GET', $occurrence['base'])['remaining']);
    }

    public function testActualYocoAdapterUsesServerIdempotencyAndAuthenticatesRawWebhookBody(): void
    {
        $calls = [];
        $http = new MockHttpClient(function ($method, $url, $options) use (&$calls) {
            self::assertFalse($this->db->isTransactionActive());
            $calls[] = [$method, $url, $options];
            $sent = json_decode($options['body'], true);

            return new MockResponse(json_encode(['id' => 'checkout_real', 'amount' => $sent['amount'], 'currency' => 'ZAR', 'processingMode' => 'live', 'redirectUrl' => 'https://c.yoco.com/checkout/test']));
        });
        $this->gateway->delegate = new HostedPaymentGateway($this->config(), $http, $this->clock);
        $order = $this->checkout();
        self::assertCount(1, $calls);
        self::assertContains('Idempotency-Key: '.$order['id'], $calls[0][2]['headers']);
        $this->clock->sleep(24 * 60 * 60);
        $event = ['id' => 'evt-1', 'type' => 'payment.succeeded', 'payload' => ['createdDate' => '2030-01-01T09:00:00Z', 'id' => 'pay_1', 'amount' => 12345, 'currency' => 'ZAR', 'mode' => 'live', 'status' => 'succeeded', 'metadata' => ['checkoutId' => 'checkout_real']]];
        $body = json_encode($event);
        $timestamp = (string) $this->clock->now()->getTimestamp();
        $secret = str_repeat('a', 32);
        $signature = base64_encode(hash_hmac('sha256', 'evt-1.'.$timestamp.'.'.$body, $secret, true));
        $this->client->request('POST', '/api/payments/webhooks/yoco', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_WEBHOOK_ID' => 'evt-1', 'HTTP_WEBHOOK_TIMESTAMP' => $timestamp, 'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$signature], content: $body);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->gifts());
        self::assertSame('2030-01-01', $this->gifts()[0]->receivedOn);
        $this->client->request('POST', '/api/payments/webhooks/yoco', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_WEBHOOK_ID' => 'evt-1', 'HTTP_WEBHOOK_TIMESTAMP' => $timestamp, 'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$signature], content: $body.' ');
        self::assertResponseStatusCodeSame(400);
        $this->clock->sleep(181);
        $this->client->request('POST', '/api/payments/webhooks/yoco', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_WEBHOOK_ID' => 'evt-1', 'HTTP_WEBHOOK_TIMESTAMP' => $timestamp, 'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$signature], content: $body);
        self::assertResponseStatusCodeSame(400);
    }

    public function testActualPayfastAdapterChecksIpSignatureMerchantAndServerValidation(): void
    {
        $http = new MockHttpClient(fn () => new MockResponse('VALID'));
        $gateway = new HostedPaymentGateway($this->config(), $http, $this->clock);
        $this->gateway->delegate = $gateway;
        $order = $this->checkout('payfast');
        self::assertSame('https://www.payfast.co.za/eng/process', $order['checkout']['url']);
        self::assertArrayNotHasKey('passphrase', $order['checkout']['fields']);
        $fields = ['m_payment_id' => $order['id'], 'pf_payment_id' => '1234', 'payment_status' => 'COMPLETE', 'amount_gross' => '123.45', 'item_description' => '', 'merchant_id' => 'merchant'];
        $encoded = http_build_query($fields, '', '&', PHP_QUERY_RFC1738);
        $body = $encoded.'&signature='.md5($encoded.'&passphrase=private-passphrase');
        $this->client->request('POST', '/api/payments/webhooks/payfast', server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'REMOTE_ADDR' => '127.0.0.1'], content: $body);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->gifts());
        $this->client->request('POST', '/api/payments/webhooks/payfast', server: ['REMOTE_ADDR' => '192.0.2.1'], content: $body);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/api/payments/webhooks/payfast', server: ['REMOTE_ADDR' => '127.0.0.1'], content: $body.'&amount_gross=0.01');
        self::assertResponseStatusCodeSame(400);
        $this->gateway->delegate = new HostedPaymentGateway($this->config(), new MockHttpClient(fn () => new MockResponse('INVALID')), $this->clock);
        $this->client->request('POST', '/api/payments/webhooks/payfast', server: ['REMOTE_ADDR' => '127.0.0.1'], content: $body);
        self::assertResponseStatusCodeSame(400);
    }

    public function testRealPayfastMonthlyFormAndCancellationApiAreSignedAndNeverRunInsideATransaction(): void
    {
        $calls = [];
        $http = new MockHttpClient(function ($method, $url, $options) use (&$calls) {
            self::assertFalse($this->db->isTransactionActive());
            $calls[] = [$method, $url, $options];
            if (str_contains($url, '/eng/query/validate')) {
                return new MockResponse('VALID');
            }

            return new MockResponse(json_encode(['code' => 200, 'status' => 'success', 'data' => ['response' => 'GET' === $method ? ['status_text' => 'ACTIVE'] : true]]));
        });
        $this->gateway->delegate = new HostedPaymentGateway($this->config(), $http, $this->clock);
        $order = $this->checkout('payfast', true);
        $form = $order['checkout']['fields'];
        self::assertSame('3', $form['frequency']);
        self::assertSame('0', $form['cycles']);
        self::assertSame('2030-02-01', $form['billing_date']);
        self::assertSame('123.45', $form['recurring_amount']);
        $token = Uuid::v4()->toRfc4122();
        $data = ['m_payment_id' => $order['id'], 'pf_payment_id' => 'recurring-payment', 'payment_status' => 'COMPLETE', 'amount_gross' => '123.45', 'merchant_id' => 'merchant', 'token' => $token];
        $encoded = http_build_query($data, '', '&', PHP_QUERY_RFC1738);
        $this->client->request('POST', '/api/payments/webhooks/payfast', server: ['REMOTE_ADDR' => '127.0.0.1'], content: $encoded.'&signature='.md5($encoded.'&passphrase=private-passphrase'));
        self::assertResponseIsSuccessful();
        $current = $this->getOrder($order['id']);
        $cancelled = $this->request('POST', '/api/payments/member/'.$order['id'].'/cancel-subscription', ['expectedVersion' => $current['version']]);
        self::assertSame('cancelled', $cancelled['subscriptionStatus']);
        self::assertSame('GET', $calls[1][0]);
        self::assertSame('PUT', $calls[2][0]);
        self::assertStringEndsWith('/subscriptions/'.$token.'/cancel', $calls[2][1]);
        $signed = ['merchant-id' => 'merchant', 'version' => 'v1', 'timestamp' => $this->clock->now()->format(DATE_ATOM), 'passphrase' => 'private-passphrase'];
        ksort($signed);
        $signature = md5(http_build_query($signed, '', '&', PHP_QUERY_RFC1738));
        self::assertContains('signature: '.$signature, $calls[2][2]['headers']);
    }

    public function testAnAdditionalOneOffCollectionIsRetainedAndFlaggedRatherThanLost(): void
    {
        $order = $this->checkout();
        $this->notify($this->event($order));
        $this->notify($this->event($order, 'second-collected-payment'));
        self::assertCount(2, $this->gifts());
        self::assertSame('review', $this->getOrder($order['id'])['status']);
        $this->notify($this->event($order, 'ambiguous-refund', 'refunded', 1000));
        $current = $this->getOrder($order['id']);
        self::assertFalse($current['refunds'][0]['applied']);
        $charge = $current['charges'][0];
        $this->request('POST', '/api/payments/charges/'.$charge['id'].'/refund', ['amountMinor' => 1000, 'reference' => 'ambiguous-refund', 'confirmed' => true, 'expectedVersion' => $charge['version']]);
        $current = $this->getOrder($order['id']);
        self::assertTrue($current['refunds'][0]['applied']);
        self::assertSame(1000, $current['charges'][0]['refundedMinor']);
        $this->expectException(DomainException::class);
        self::getContainer()->get(\App\Giving\Application\Command\ReverseGift\ReverseGiftHandler::class)(new \App\Giving\Application\Command\ReverseGift\ReverseGiftCommand($this->gifts()[0]->id, '2030-01-01', 'Bypass payment ledger'));
    }

    public function testAProviderReferenceCannotBeReusedForAnotherPersonsOrder(): void
    {
        $first = $this->checkout();
        $this->notify($this->event($first));
        $second = $this->checkout(key: 'different-order');
        $event = $this->event($second);
        $event['eventId'] = 'another-event';
        $this->notify($event);
        self::assertCount(1, $this->gifts());
        self::assertSame('review', $this->getOrder($second['id'])['status']);
    }

    private function config(): PaymentConfiguration
    {
        return new PaymentConfiguration('https://church.example.test', 'live', 'merchant', 'key', 'private-passphrase', '127.0.0.1/32', 'live', 'sk_live_test-only', 'whsec_'.base64_encode(str_repeat('a', 32)));
    }
}
final class PaymentAccess implements AuthorizationCheckerInterface
{
    public array $codes = ['member.portal', 'payments.manage', 'giving.manage', 'events.read', 'events.manage', 'people.read'];

    public function isGranted(mixed $attribute, mixed $subject = null, ?\Symfony\Component\Security\Core\Authorization\AccessDecision $accessDecision = null): bool
    {
        return in_array($attribute, $this->codes, true);
    }
}
final class TestPaymentGateway implements PaymentGateway
{
    public ?PaymentGateway $delegate = null;
    public int $calls = 0;
    public array $ids = [];
    public bool $failCheckout = false;
    public bool $failCancel = false;
    public string $environment = 'live';

    public function __construct(private Connection $db)
    {
    }

    public function providers(): array
    {
        return [['id' => 'yoco', 'name' => 'Yoco', 'available' => true, 'mode' => $this->environment, 'recurring' => false], ['id' => 'payfast', 'name' => 'Payfast', 'available' => true, 'mode' => $this->environment, 'recurring' => true]];
    }

    public function mode(string $provider): string
    {
        return $this->delegate?->mode($provider) ?? $this->environment;
    }

    public function checkout(PaymentOrder $order, string $name, string $email): array
    {
        OnlinePaymentsHttpTest::assertFalse($this->db->isTransactionActive());
        if ($this->delegate) {
            return $this->delegate->checkout($order, $name, $email);
        }
        ++$this->calls;
        $this->ids[] = $order->id;
        if ($this->failCheckout) {
            throw new RuntimeException('Checkout unconfirmed; retry the same request.');
        }

        return ['id' => 'yoco' === $order->provider ? 'checkout_'.$order->id : $order->id, 'url' => 'https://c.yoco.com/checkout/test', 'method' => 'GET', 'fields' => []];
    }

    public function verify(string $provider, string $body, array $headers, string $clientIp): ?array
    {
        return $this->delegate ? $this->delegate->verify($provider, $body, $headers, $clientIp) : json_decode($body, true);
    }

    public function cancelSubscription(PaymentOrder $order, string $token): void
    {
        OnlinePaymentsHttpTest::assertFalse($this->db->isTransactionActive());
        if ($this->delegate) {
            $this->delegate->cancelSubscription($order, $token);

            return;
        }
        if ($this->failCancel) {
            throw new RuntimeException('Cancellation unconfirmed.');
        }
    }

    public function subscriptionStatus(PaymentOrder $order, string $token): string
    {
        OnlinePaymentsHttpTest::assertFalse($this->db->isTransactionActive());

        return $this->delegate ? $this->delegate->subscriptionStatus($order, $token) : 'cancelled';
    }
}
final class PaymentsHttpKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getCacheDir(): string
    {
        return '/tmp/onechurch-payments-tests/cache';
    }

    public function getLogDir(): string
    {
        return '/tmp/onechurch-payments-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'payments-test-only', 'test' => true, 'router' => ['utf8' => true], 'serializer' => ['enabled' => true], 'validation' => ['enabled' => true], 'messenger' => ['enabled' => true], 'exceptions' => [AccessDeniedException::class => ['status_code' => 403]]]);
        $services = $container->services()->defaults()->autowire()->autoconfigure();
        \App\Tests\Support\ApplicationPorts::reads($services);
        \App\Tests\Support\ApplicationPorts::identity($services);
        $services->set(\App\Shared\Infrastructure\Http\DomainExceptionListener::class);
        foreach ([Connection::class, ClockInterface::class, PaymentGateway::class, PersonDirectory::class, UserAccountRepository::class, PasswordHasher::class, TokenStorageInterface::class, AuthorizationCheckerInterface::class] as $class) {
            $services->set($class)->synthetic()->public();
        }
        $services->alias('security.authorization_checker', AuthorizationCheckerInterface::class);
        $services->set(\Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener::class);
        foreach ([\App\IdentityAccess\Application\Query\GetAuthenticatedAccount\GetAuthenticatedAccountHandler::class, \App\IdentityAccess\Application\Query\GetAccountSecurityState\GetAccountSecurityStateHandler::class, \App\IdentityAccess\Application\Query\GetAccountSecurityStatus\GetAccountSecurityStatusHandler::class, \App\IdentityAccess\Application\Query\IsAccountVerified\IsAccountVerifiedHandler::class, \App\IdentityAccess\Application\Query\RequireVerifiedAccount\RequireVerifiedAccountHandler::class, \App\IdentityAccess\Application\Command\SetupAuthenticator\SetupAuthenticatorHandler::class, \App\IdentityAccess\Application\Command\EnableAuthenticator\EnableAuthenticatorHandler::class, \App\IdentityAccess\Application\Command\VerifyAuthenticator\VerifyAuthenticatorHandler::class, \App\IdentityAccess\Application\Command\RegenerateRecoveryCodes\RegenerateRecoveryCodesHandler::class, \App\IdentityAccess\Application\Command\ChangeOwnPassword\ChangeOwnPasswordHandler::class, \App\IdentityAccess\Application\Command\InvalidateMemberAccess\InvalidateMemberAccessHandler::class, \App\IdentityAccess\Application\Command\DisableAccountAndSessions\DisableAccountAndSessionsHandler::class, \App\IdentityAccess\Application\Command\RecoverAccount\RecoverAccountHandler::class, \App\IdentityAccess\Application\Command\IssueAdministratorRecovery\IssueAdministratorRecoveryHandler::class] as $handler) {
            $services->set($handler);
        }
        $services->set(\App\Shared\Infrastructure\ReadModel\DbalWorkflowAuditReader::class);
        $services->alias(\App\Shared\Application\ReadModel\WorkflowAuditReader::class, \App\Shared\Infrastructure\ReadModel\DbalWorkflowAuditReader::class);
        $services->set(\App\Shared\Presentation\Http\WorkflowAuditController::class);
        $services->set(\Symfony\Component\Lock\Store\FlockStore::class);
        $services->set(\Symfony\Component\Lock\LockFactory::class)->args([\Symfony\Component\DependencyInjection\Loader\Configurator\service(\Symfony\Component\Lock\Store\FlockStore::class)]);
        $base = dirname(__DIR__, 2).'/src';
        $services->load('App\Shared\Infrastructure\Persistence\\', $base.'/Shared/Infrastructure/Persistence/')->exclude([$base.'/Shared/Infrastructure/Persistence/WorkflowDocumentMapper.php', $base.'/Shared/Infrastructure/Persistence/WorkflowDocumentRepository.php']);
        foreach (['Giving', 'Events', 'Payments'] as $context) {
            foreach (['Application', 'Infrastructure', 'Presentation'] as $section) {
                $excluded = 'Presentation' === $section && is_dir($base.'/'.$context.'/Presentation/Http/DTO') ? [$base.'/'.$context.'/Presentation/Http/DTO/'] : [];
                if ('Events' === $context && 'Application' === $section) {
                    $excluded = [$base.'/Events/Application/Command/CreateEvent.php', $base.'/Events/Application/Command/UpdateEvent.php', $base.'/Events/Application/Command/UpdateOccurrence.php'];
                }
                if ('Payments' === $context && 'Infrastructure' === $section) {
                    $excluded = [$base.'/Payments/Infrastructure/Gateway/HostedPaymentGateway.php', $base.'/Payments/Infrastructure/Gateway/PaymentConfiguration.php'];
                }
                if ('Payments' === $context && 'Presentation' === $section) {
                    $excluded = glob($base.'/Payments/Presentation/Http/*Payload.php');
                }
                $services->load('App\\'.$context.'\\'.$section.'\\', $base.'/'.$context.'/'.$section.'/')->exclude($excluded);
            }
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $base = dirname(__DIR__, 2).'/src';
        $routes->import($base.'/Shared/Presentation/Http/WorkflowAuditController.php', 'attribute');
        $routes->import($base.'/Payments/Presentation/Http/', 'attribute');
        $routes->import($base.'/Events/Presentation/Http/Controller/', 'attribute');
    }
}

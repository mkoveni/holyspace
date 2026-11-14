<?php

declare(strict_types=1);

namespace App\Tests\Payments\Support;

use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Application\Contract\PaymentProvider;
use App\Payments\Application\Contract\ProviderNotification;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Java's PaymentIntegrationTestSupport: an administrator (holding every payments permission through the seeded role),
 * scripted provider doubles that survive between requests, and helpers for donors, funds and notifications.
 */
abstract class PaymentApiTestCase extends IdentityApiTestCase
{
    protected string $admin;
    protected string $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $this->gateway()->reset();
        $this->http()->reset();
        $this->probe()->reset();
        $this->ledger()->failPosts = 0;
        $this->adminId = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
    }

    protected function gateway(): ScriptedPaymentGateway
    {
        $gateway = self::getContainer()->get(ScriptedPaymentGateway::class);
        \assert($gateway instanceof ScriptedPaymentGateway);

        return $gateway;
    }

    protected function http(): ScriptedProviderHttp
    {
        $http = self::getContainer()->get(ScriptedProviderHttp::class);
        \assert($http instanceof ScriptedProviderHttp);

        return $http;
    }

    protected function probe(): ScriptedConnectionProbe
    {
        $probe = self::getContainer()->get(ScriptedConnectionProbe::class);
        \assert($probe instanceof ScriptedConnectionProbe);

        return $probe;
    }

    protected function ledger(): FailingGivingLedger
    {
        $ledger = self::getContainer()->get(FailingGivingLedger::class);
        \assert($ledger instanceof FailingGivingLedger);

        return $ledger;
    }

    protected function today(): CalendarDate
    {
        $calendar = self::getContainer()->get(ChurchCalendar::class);
        \assert($calendar instanceof ChurchCalendar);

        return $calendar->today();
    }

    /** Scripts a provider gateway in $mode whose checkouts are Yoco redirects (Java's mocked PaymentGateway). */
    protected function scriptGateway(string $mode): void
    {
        $gateway = $this->gateway();
        $gateway->scripted = true;
        $gateway->mode = static fn (): string => $mode;
        $gateway->providers = static fn (): array => [new PaymentProvider('yoco', 'Yoco', true, $mode, false)];
        $gateway->checkout ??= static fn ($order): HostedCheckout => new HostedCheckout('co_'.$order->id, 'https://c.yoco.com/checkout/one', []);
    }

    /** Delivers an authenticated (scripted) Yoco notification for $order, giving the order a checkout first if it has none. */
    protected function event(string $order, string $type, string $reference, int $amount = 10000, string $mode = 'live'): int
    {
        $date = $this->today();
        $checkout = 'co_'.$order;
        if (null === $this->connection->fetchOne('SELECT checkout_id FROM payments_orders WHERE id = ?', [$order])) {
            $this->connection->executeStatement('ALTER TABLE payments_orders DISABLE TRIGGER payments_order_guard');
            $this->connection->executeStatement('UPDATE payments_orders SET checkout_id = ? WHERE id = ?', [$checkout, $order]);
            $this->connection->executeStatement('ALTER TABLE payments_orders ENABLE TRIGGER payments_order_guard');
        } else {
            $checkout = (string) $this->connection->fetchOne('SELECT checkout_id FROM payments_orders WHERE id = ?', [$order]);
        }
        $this->gateway()->verify = static fn (): ProviderNotification => new ProviderNotification('yoco', $mode, $reference.':'.$type, $type, $reference, $checkout, $order, $amount, 'ZAR', $date);
        $this->call('POST', '/api/payments/webhooks/yoco', null, ['event' => $reference]);

        return $this->code();
    }

    protected function person(string $name): string
    {
        $this->call('POST', '/api/people', $this->admin, ['person' => ['firstName' => $name, 'lastName' => 'Mabasa']]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    protected function fund(string $name): string
    {
        $this->call('POST', '/api/giving/funds', $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'name' => $name]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    protected function contact(string $person, string $email): string
    {
        $this->call('GET', "/api/people/$person", $this->admin);
        $this->call('POST', "/api/people/$person/contacts", $this->admin, ['expectedVersion' => (int) $this->json()['person']['version'], 'kind' => 'EMAIL', 'value' => $email, 'primary' => true]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @param array<string, mixed> $body */
    protected function created(string $uri, array $body, ?string $token = null): string
    {
        $this->call('POST', $uri, $token ?? $this->admin, $body);
        self::assertSame(201, $this->code(), (string) $this->client->getResponse()->getContent());

        return (string) $this->json()['id'];
    }

    protected function body(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    protected function revoke(string $code): void
    {
        $this->connection->executeStatement(
            'DELETE FROM role_permissions WHERE role_id = ? AND permission_id = (SELECT id FROM permissions WHERE code = ?)',
            [self::ADMIN_ROLE, $code],
        );
    }

    protected function version(string $order): int
    {
        return (int) $this->connection->fetchOne('SELECT version FROM payments_orders WHERE id = ?', [$order]);
    }

    protected function chargeOf(string $order): string
    {
        return (string) $this->connection->fetchOne('SELECT id FROM payments_charges WHERE order_id = ? ORDER BY created_at, id LIMIT 1', [$order]);
    }

    /** A staff payment (Java `order(provider)`) for a new donor and fund. */
    protected function order(string $provider = 'yoco', ?string $donor = null, ?string $fund = null): string
    {
        return $this->created('/api/payments', ['requestId' => Uuid::v4()->toRfc4122(), 'donorId' => $donor ?? $this->person('Donor'),
            'fundId' => $fund ?? $this->fund('General '.Uuid::v4()->toRfc4122()), 'amountMinor' => 10000, 'provider' => $provider]);
    }
}

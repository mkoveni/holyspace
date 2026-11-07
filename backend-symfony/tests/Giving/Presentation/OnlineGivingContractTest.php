<?php

declare(strict_types=1);

namespace App\Tests\Giving\Presentation;

use App\Giving\Application\Contract\DonorCheckoutGuard;
use App\Giving\Application\Contract\OnlineGivingLedger;
use App\Giving\Domain\GivingException;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\UnitOfWork;
use App\Shared\Domain\CalendarDate;
use App\Tests\Support\IdentityApiTestCase;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * The trusted Payments contracts called as Payments will call them (from its own transaction), with the Giving outcomes
 * Java's PaymentsApiIntegrationTest asserts: one gift per verified charge, no manual reversal, dated idempotent partial
 * refunds that void acknowledgements, release pledge credit and appear on statements, and case-sensitive references.
 */
final class OnlineGivingContractTest extends IdentityApiTestCase
{
    private string $admin;
    private string $actor;
    private string $donor;
    private string $fund;
    private CalendarDate $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
        $this->call('POST', '/api/people', $this->admin, ['person' => ['firstName' => 'Donor', 'lastName' => 'Mabasa']]);
        self::assertSame(201, $this->code(), $this->message());
        $this->donor = (string) $this->json()['id'];
        $this->call('POST', '/api/giving/funds', $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'name' => 'General']);
        self::assertSame(201, $this->code(), $this->message());
        $this->fund = (string) $this->json()['id'];
        $calendar = self::getContainer()->get(ChurchCalendar::class);
        \assert($calendar instanceof ChurchCalendar);
        $this->today = $calendar->today();
    }

    private function ledger(): OnlineGivingLedger
    {
        $ledger = self::getContainer()->get(OnlineGivingLedger::class);
        \assert($ledger instanceof OnlineGivingLedger);

        return $ledger;
    }

    /**
     * Runs the call in one transaction, as a Payments command handler on the bus does.
     *
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    private function inPayments(callable $call): mixed
    {
        $transaction = self::getContainer()->get(UnitOfWork::class);
        \assert($transaction instanceof UnitOfWork);

        return $transaction->execute($call);
    }

    private function post(string $charge, string $reference = 'payfast:live:charge_1', int $amount = 10000): string
    {
        return $this->inPayments(fn (): string => $this->ledger()->post($charge, $this->donor, $this->fund, $amount, $reference, $this->today, $this->actor));
    }

    private function refund(string $id, string $gift, int $amount, string $reference = 'payfast:live:refund_1'): void
    {
        $this->inPayments(fn () => $this->ledger()->refund($id, $gift, $amount, $reference, $this->today, $this->actor));
    }

    private static function assertConflict(string $message, callable $call): void
    {
        try {
            $call();
            self::fail('Expected a conflict: '.$message);
        } catch (GivingException $error) {
            self::assertSame($message, $error->getMessage());
        }
    }

    private function net(): string
    {
        $this->call('GET', "/api/giving/totals?from={$this->today->iso()}&to={$this->today->iso()}", $this->admin);
        self::assertSame(200, $this->code(), $this->message());

        return (string) $this->json()[0]['netMinor'];
    }

    public function testAVerifiedChargePostsExactlyOneGiftThatOnlyPaymentsCanCorrect(): void
    {
        self::assertSame('Donor Mabasa', $this->inPayments(fn () => $this->ledger()->validate($this->donor, $this->fund))->name);
        $charge = Uuid::v4()->toRfc4122();

        self::assertSame($charge, $this->post($charge));
        self::assertSame($charge, $this->post($charge), 'a redelivered verification posts nothing new');
        self::assertSame([1, 1, 1], [$this->rows('giving_gifts'), $this->rows('giving_payment_sources'), $this->rows('giving_history', "action = 'PAYMENT_POSTED'")]);
        self::assertConflict('This request identifier was already used for different details.', fn () => $this->post($charge, amount: 9000));

        $this->call('GET', "/api/giving/gifts/$charge", $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        $detail = $this->json();
        self::assertSame(['ONLINE', 'payfast:live:charge_1', 'ZAR', 10000, 'Donor Mabasa'], [
            $detail['gift']['method'], $detail['gift']['externalReference'], $detail['gift']['money']['currency'], $detail['gift']['money']['amountMinor'], $detail['donorName'],
        ]);
        self::assertSame(['PAYMENT_POSTED', 'payfast:live:charge_1'], [$detail['history'][0]['action'], $detail['history'][0]['reason']]);

        $this->call('POST', "/api/giving/gifts/$charge/reversals", $this->admin, [
            'requestId' => Uuid::v4()->toRfc4122(), 'reversedOn' => $this->today->iso(), 'reason' => 'Cannot bypass Payments',
        ]);
        self::assertSame([409, 'Use Online payments to record a provider-completed refund.'], [$this->code(), $this->message()]);
        self::assertSame(0, $this->rows('giving_reversals'));
    }

    public function testPartialRefundsAreDatedIdempotentAndCannotExceedTheGift(): void
    {
        $gift = $this->post(Uuid::v4()->toRfc4122(), 'payfast:live:charged');
        $refund = Uuid::v4()->toRfc4122();

        $this->refund($refund, $gift, 2500);
        $this->refund($refund, $gift, 2500);
        self::assertSame([1, 1], [$this->rows('giving_payment_refunds'), $this->rows('giving_history', "action = 'PAYMENT_REFUNDED'")]);
        self::assertSame('7500', $this->net());
        self::assertConflict('Refund identity conflicts with existing evidence.', fn () => $this->refund($refund, $gift, 2600));
        self::assertConflict('Refund must match the unrefunded online gift and its dates.', fn () => $this->refund(Uuid::v4()->toRfc4122(), $gift, 8000, 'payfast:live:refund_2'));
        self::assertConflict('The Giving record was not found.', fn () => $this->refund(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122(), 100));
        self::assertSame(1, $this->rows('giving_payment_refunds'));
    }

    public function testARefundVoidsTheAcknowledgementReleasesPledgeCreditAndAppearsOnTheStatement(): void
    {
        $gift = $this->post(Uuid::v4()->toRfc4122(), 'payfast:live:original');
        $this->call('POST', "/api/giving/gifts/$gift/acknowledgements", $this->admin, ['requestId' => Uuid::v4()->toRfc4122()]);
        self::assertSame(201, $this->code(), $this->message());
        $acknowledgement = (string) $this->json()['id'];
        $this->call('POST', '/api/giving/pledges', $this->admin, [
            'requestId' => Uuid::v4()->toRfc4122(), 'donorId' => $this->donor, 'fundId' => $this->fund, 'amountMinor' => 10000, 'currency' => 'ZAR',
            'startsOn' => $this->today->plusDays(-1)->iso(), 'endsOn' => $this->today->plusDays(1)->iso(),
        ]);
        self::assertSame(201, $this->code(), $this->message());
        $pledge = (string) $this->json()['id'];
        $assign = fn (int $amount, int $version) => $this->call('POST', "/api/giving/pledges/$pledge/assignments", $this->admin, [
            'requestId' => Uuid::v4()->toRfc4122(), 'giftId' => $gift, 'amountMinor' => $amount, 'expectedVersion' => $version,
        ]);
        $assign(10000, 0);
        self::assertSame(201, $this->code(), $this->message());

        $this->refund(Uuid::v4()->toRfc4122(), $gift, 2500, 'payfast:live:part-refund');

        $this->call('GET', "/api/giving/acknowledgements/$acknowledgement", $this->admin);
        self::assertTrue($this->json()['voided']);
        $this->call('GET', "/api/giving/pledges/$pledge", $this->admin);
        $detail = $this->json();
        self::assertSame([0, 2], [$detail['summary']['creditedMinor'], $detail['summary']['pledge']['version']]);
        self::assertSame(['CANCELLED', 'Source gift refunded; review the remaining credit.'], [
            $detail['assignments'][0]['assignment']['status'], $detail['assignments'][0]['assignment']['cancellationReason'],
        ]);
        self::assertSame(['PAYMENT_REFUND', 'payfast:live:part-refund'], [$detail['history'][0]['action'], $detail['history'][0]['reason']]);
        $this->call('GET', "/api/giving/pledges/$pledge/candidates", $this->admin);
        self::assertSame(7500, $this->json()[0]['availableMinor']);
        $this->call('GET', "/api/giving/statements/{$this->donor}?from={$this->today->iso()}&to={$this->today->iso()}", $this->admin);
        $statement = $this->json();
        self::assertSame(['REFUND', -2500], [$statement['lines'][1]['kind'], $statement['lines'][1]['amountMinor']]);
        self::assertSame('7500', $statement['totals'][0]['netMinor']);

        $assign(7501, 2);
        self::assertSame(409, $this->code());
        $assign(7500, 2);
        self::assertSame(201, $this->code(), $this->message());
    }

    public function testProviderReferencesAreCaseSensitiveAndUniquePerCharge(): void
    {
        $this->post(Uuid::v4()->toRfc4122(), 'payfast:live:charge_ABC');
        $this->post(Uuid::v4()->toRfc4122(), 'payfast:live:charge_abc');
        self::assertSame(2, $this->rows('giving_gifts'));

        self::assertConflict(GivingException::evidenceConflict()->getMessage(), fn () => $this->post(Uuid::v4()->toRfc4122(), 'payfast:live:charge_ABC'));
        self::assertSame(2, $this->rows('giving_gifts'));
    }

    public function testAPostRollsBackWithTheCallersTransaction(): void
    {
        $charge = Uuid::v4()->toRfc4122();
        try {
            $this->inPayments(function () use ($charge): void {
                $this->ledger()->post($charge, $this->donor, $this->fund, 10000, 'payfast:live:lost', $this->today, $this->actor);
                throw new RuntimeException('Payments failed after posting');
            });
        } catch (RuntimeException $error) {
            self::assertSame('Payments failed after posting', $error->getMessage());
        }
        self::assertSame([0, 0, 0], [$this->rows('giving_gifts'), $this->rows('giving_payment_sources'), $this->rows('giving_history', "action = 'PAYMENT_POSTED'")]);

        self::assertSame($charge, $this->post($charge, 'payfast:live:lost'), 'the retained evidence can be posted again');
    }

    public function testTheCheckoutGuardAcceptsOnlyTheVerifiedDonorOfTheAccount(): void
    {
        $account = $this->account('donor');
        $guard = static function (string $donor) use ($account): bool {
            $guard = self::getContainer()->get(DonorCheckoutGuard::class);
            \assert($guard instanceof DonorCheckoutGuard);

            return $guard->lockEligible($account, $donor);
        };
        self::assertFalse($this->inPayments(fn () => $guard($this->donor)), 'an unlinked account');

        $this->call('PUT', "/api/giving/donor-access/$account", $this->admin, [
            'personId' => $this->donor, 'active' => true, 'expectedVersion' => 0, 'evidence' => 'Verified account holder identity in person',
        ]);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
        self::assertTrue($this->inPayments(fn () => $guard($this->donor)));
        self::assertFalse($this->inPayments(fn () => $guard(Uuid::v4()->toRfc4122())), 'another person');

        $this->call('PUT', "/api/giving/donor-access/$account", $this->admin, [
            'personId' => $this->donor, 'active' => false, 'expectedVersion' => 1, 'evidence' => 'Account holder asked to stop giving online',
        ]);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
        self::assertFalse($this->inPayments(fn () => $guard($this->donor)), 'a revoked link');
    }
}

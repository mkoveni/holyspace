<?php

declare(strict_types=1);

namespace App\Tests\Giving\Infrastructure;

use App\Giving\Application\Command\AssignPledgeGift\AssignPledgeGiftCommand;
use App\Giving\Application\Command\AssignPledgeGift\AssignPledgeGiftHandler;
use App\Giving\Application\Command\CreateReconciliation\CreateReconciliationCommand;
use App\Giving\Application\Command\CreateReconciliation\CreateReconciliationHandler;
use App\Giving\Application\Command\IssueAcknowledgement\IssueAcknowledgementCommand;
use App\Giving\Application\Command\IssueAcknowledgement\IssueAcknowledgementHandler;
use App\Giving\Application\Command\RecordGift\RecordGiftCommand;
use App\Giving\Application\Command\RecordGift\RecordGiftHandler;
use App\Giving\Application\Command\ReverseGift\ReverseGiftCommand;
use App\Giving\Application\Command\ReverseGift\ReverseGiftHandler;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\OnlineGivingService;
use App\Giving\Domain\Allocation;
use App\Giving\Infrastructure\Persistence\DbalAcknowledgementStore;
use App\Giving\Infrastructure\Persistence\DbalFundStore;
use App\Giving\Infrastructure\Persistence\DbalGiftStore;
use App\Giving\Infrastructure\Persistence\DbalGivingHistory;
use App\Giving\Infrastructure\Persistence\DbalGivingReader;
use App\Giving\Infrastructure\Persistence\DbalOnlineGivingStore;
use App\Giving\Infrastructure\Persistence\DbalPledgeStore;
use App\Giving\Infrastructure\Persistence\DbalReconciliationStore;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\DbalUnitOfWork;
use App\Shared\Infrastructure\SymfonyIdGenerator;
use App\Tests\Giving\Application\FakePeople;
use App\Tests\Giving\Application\RecordingPermissions;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * The real Giving use cases wired to one database session, so two sessions can contend for the same rows. The caller owns
 * the transaction, as the command bus does in production.
 */
final readonly class GivingSession
{
    private GivingAccess $access;
    private DbalGiftStore $gifts;
    private DbalFundStore $funds;
    private DbalPledgeStore $pledges;
    private DbalGivingHistory $history;
    private ChurchCalendar $calendar;

    public function __construct(private Connection $connection, private string $actor, private FakePeople $people, private ClockInterface $clock)
    {
        $this->access = new GivingAccess(new RecordingPermissions($actor, ['giving.manage', 'giving.reverse', 'giving.reconcile', 'giving.acknowledge']));
        $this->gifts = new DbalGiftStore($connection);
        $this->funds = new DbalFundStore($connection);
        $this->pledges = new DbalPledgeStore($connection);
        $this->history = new DbalGivingHistory($connection, new SymfonyIdGenerator());
        $this->calendar = new ChurchCalendar($clock, 'Africa/Johannesburg');
    }

    /** @param list<Allocation> $allocations */
    public function record(string $requestId, string $method, ?string $reference, int $amountMinor, array $allocations, CalendarDate $receivedOn): string
    {
        return new RecordGiftHandler($this->access, $this->gifts, $this->funds, $this->history, $this->people, $this->calendar, $this->clock)
            ->handle(new RecordGiftCommand($this->actor, $requestId, null, $amountMinor, 'ZAR', $receivedOn, $method, $reference, $allocations));
    }

    public function reverse(string $requestId, string $giftId, CalendarDate $reversedOn): string
    {
        return new ReverseGiftHandler($this->access, $this->gifts, $this->history, $this->calendar, $this->clock)
            ->handle(new ReverseGiftCommand($this->actor, $requestId, $giftId, $reversedOn, 'Duplicate entry'));
    }

    public function assign(string $requestId, string $pledgeId, string $giftId, int $amountMinor): string
    {
        return new AssignPledgeGiftHandler($this->access, $this->gifts, $this->pledges, $this->history, $this->clock)
            ->handle(new AssignPledgeGiftCommand($this->actor, $requestId, $pledgeId, $giftId, 0, $amountMinor));
    }

    public function reconcile(string $requestId, string $reference, string $giftId, int $expectedMinor, CalendarDate $reconciledOn): string
    {
        return new CreateReconciliationHandler($this->access, $this->gifts, new DbalReconciliationStore($this->connection), $this->history, $this->calendar, $this->clock)
            ->handle(new CreateReconciliationCommand($this->actor, $requestId, $reference, $expectedMinor, 'ZAR', $reconciledOn, null, [$giftId]));
    }

    public function acknowledge(string $requestId, string $giftId): string
    {
        return new IssueAcknowledgementHandler(
            $this->access,
            $this->gifts,
            new DbalAcknowledgementStore($this->connection),
            new DbalGivingReader($this->connection, $this->people),
            $this->history,
            $this->calendar,
            $this->clock,
        )->handle(new IssueAcknowledgementCommand($this->actor, $requestId, $giftId));
    }

    public function postOnline(string $chargeId, string $donorId, string $fundId, int $amountMinor, string $reference, CalendarDate $receivedOn): string
    {
        return new OnlineGivingService(
            $this->gifts,
            $this->funds,
            new DbalOnlineGivingStore($this->connection),
            $this->pledges,
            $this->history,
            $this->people,
            new DbalUnitOfWork($this->connection),
            $this->calendar,
            $this->clock,
        )->post($chargeId, $donorId, $fundId, $amountMinor, $reference, $receivedOn, $this->actor);
    }
}

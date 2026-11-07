<?php

declare(strict_types=1);

namespace App\Tests\Communications\Application;

use App\Communications\Application\Contract\CommunicationAudit;
use App\Communications\Application\Contract\DeliveryEligibility;
use App\Communications\Application\Contract\DeliveryEvent;
use App\Communications\Application\Contract\DeliveryPage;
use App\Communications\Application\Contract\DeliveryReader;
use App\Communications\Application\Contract\DeliveryReceipt;
use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\DeliverySources;
use App\Communications\Application\Contract\EmailTransport;
use App\Communications\Application\Contract\TransportConfiguration;
use App\Communications\Application\Contract\TransportResult;
use App\Communications\Application\DeliveryMapping;
use App\Communications\Application\DispatchEligibility;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\EmailDelivery;
use App\Communications\Domain\Repository\DeliveryRepository;
use App\Shared\Application\UnitOfWork;
use DateTimeImmutable;
use Throwable;

/**
 * One in-memory world: delivery rows, a transport that records when it was called and inside which transaction,
 * and one source context whose eligibility tests can switch off. Everything writes to $log in order.
 */
final class CommunicationWorld implements DeliveryRepository, DeliveryReader, DeliverySources, CommunicationAudit, EmailTransport, DeliveryEligibility, UnitOfWork
{
    public const string SOURCE = 'children-ministry';

    /** @var array<string, EmailDelivery> */
    public array $rows = [];
    /** @var list<string> */
    public array $log = [];
    /** @var array<string, string> */
    public array $contexts = [];
    /** @var list<array{string, string, ?string, ?string, ?string, string}> */
    public array $events = [];
    public int $depth = 0;
    public bool $eligible = true;
    public bool $enabled = true;
    public bool $capture = false;
    public ?string $deniedOperation = null;
    public string $sourceName = self::SOURCE;
    /** @var list<int> transaction depth at each send */
    public array $sendDepths = [];
    public TransportResult $result;
    public ?Throwable $sendFailure = null;
    public int $sends = 0;
    /** @var (callable(self): void)|null runs after the transport returns, to simulate a concurrent change */
    public $hijack;

    public function __construct()
    {
        $this->result = new TransportResult('SENT', '', false);
    }

    /** @return list<string> */
    public function entries(): array
    {
        return $this->log;
    }

    public function eligibility(): DispatchEligibility
    {
        return new DispatchEligibility($this, [$this]);
    }

    public function execute(callable $operation): mixed
    {
        ++$this->depth;
        try {
            return $operation();
        } finally {
            --$this->depth;
        }
    }

    public function insert(EmailDelivery $delivery): void
    {
        $this->log[] = 'insert';
        $this->rows[$delivery->id] = $delivery;
    }

    public function lock(string $id): ?EmailDelivery
    {
        $this->log[] = 'lock';

        return $this->rows[$id] ?? null;
    }

    public function save(EmailDelivery $delivery): void
    {
        $this->log[] = 'save:'.$delivery->status;
        $this->rows[$delivery->id] = $delivery;
    }

    public function event(string $deliveryId, string $type, DateTimeImmutable $at, ?string $actorId, ?string $eventKey, ?string $eventHash, string $reference): void
    {
        $this->log[] = 'event:'.$type;
        $this->events[] = [$deliveryId, $type, $actorId, $eventKey, $eventHash, $reference];
    }

    public function recoverStale(DateTimeImmutable $before, DateTimeImmutable $now): int
    {
        $this->log[] = 'recover';
        $count = 0;
        foreach ($this->rows as $id => $row) {
            if ('SENDING' === $row->status && null !== $row->startedAt && $row->startedAt < $before) {
                $this->rows[$id] = $row->transition('UNKNOWN', $row->scheduledAt, $now, 'PROCESS_INTERRUPTED', false);
                ++$count;
            }
        }

        return $count;
    }

    public function summaries(array $communicationIds): array
    {
        return [];
    }

    public function delivery(string $id): ?DeliverySnapshot
    {
        return isset($this->rows[$id]) ? DeliveryMapping::snapshot($this->rows[$id]) : null;
    }

    public function forCommunication(string $communicationId): ?DeliverySnapshot
    {
        return null;
    }

    public function events(string $deliveryId, int $limit, int $offset): array
    {
        return array_values(array_map(static fn (array $e): DeliveryEvent => new DeliveryEvent('e', $e[1], new DateTimeImmutable(), $e[5]), array_filter($this->events, static fn (array $e): bool => $e[0] === $deliveryId)));
    }

    public function list(?string $status, int $limit, int $offset): DeliveryPage
    {
        return new DeliveryPage([], 0, 0, 0);
    }

    public function listForSource(string $sourceContext, ?string $status, int $limit, int $offset): DeliveryPage
    {
        return new DeliveryPage([], 0, 0, 0);
    }

    public function belongsTo(string $id, string $sourceContext): bool
    {
        return ($this->contexts[$id] ?? null) === $sourceContext;
    }

    public function due(DateTimeImmutable $at, int $limit): array
    {
        $due = array_filter($this->rows, static fn (EmailDelivery $d): bool => $d->waiting() && $d->scheduledAt <= $at);
        usort($due, static fn (EmailDelivery $a, EmailDelivery $b): int => [$a->scheduledAt, $a->id] <=> [$b->scheduledAt, $b->id]);

        return array_slice(array_map(static fn (EmailDelivery $d): string => $d->id, $due), 0, $limit);
    }

    public function receipt(string $eventKey): ?DeliveryReceipt
    {
        foreach ($this->events as $event) {
            if ($event[3] === $eventKey) {
                return new DeliveryReceipt((string) $event[4], $event[0]);
            }
        }

        return null;
    }

    public function context(string $deliveryId): ?string
    {
        return $this->contexts[$deliveryId] ?? null;
    }

    public function associate(string $deliveryId, string $context): void
    {
        $this->log[] = 'associate';
        $this->contexts[$deliveryId] = $context;
    }

    public function record(string $actor, string $action, string $target, string $detail, DateTimeImmutable $at): void
    {
        $this->log[] = 'audit:'.$action;
    }

    public function configuration(): TransportConfiguration
    {
        return new TransportConfiguration($this->enabled, $this->capture, 'SMTP');
    }

    public function send(DeliverySnapshot $delivery): TransportResult
    {
        ++$this->sends;
        $this->sendDepths[] = $this->depth;
        $this->log[] = 'send:'.$delivery->status;
        if (null !== $this->sendFailure) {
            throw $this->sendFailure;
        }
        if (null !== $this->hijack) {
            ($this->hijack)($this);
        }

        return $this->result;
    }

    public function sourceContext(): string
    {
        return $this->sourceName;
    }

    public function authorize(DeliverySnapshot $delivery, string $actor, string $operation): void
    {
        $this->log[] = 'authorize:'.$operation;
        if ($this->deniedOperation === $operation) {
            throw CommunicationException::forbidden();
        }
    }

    public function eligible(DeliverySnapshot $delivery, DateTimeImmutable $at): bool
    {
        $this->log[] = 'eligible';

        return $this->eligible;
    }

    public function snapshot(string $id = 'd1', string $status = 'QUEUED', string $scheduled = '2026-06-01T10:00:00Z', int $attempts = 0, int $version = 0, string $messageId = '<d1@onechurch.delivery>'): DeliverySnapshot
    {
        return new DeliverySnapshot($id, 'c-'.$id, 'v', 'k', 'guardian@example.org', 'Subject', 'Body', str_repeat('a', 64), 'approver', new DateTimeImmutable('2026-06-01T09:00:00Z'), new DateTimeImmutable($scheduled), $status, $attempts, $messageId, null, null, null, '', false, $version);
    }

    public function seed(string $status = 'QUEUED', string $id = 'd1', string $scheduled = '2026-06-01T10:00:00Z', int $attempts = 0, ?string $messageId = null): EmailDelivery
    {
        $delivery = DeliveryMapping::aggregate($this->snapshot($id, $status, $scheduled, $attempts, 0, $messageId ?? '<'.$id.'@onechurch.delivery>'));
        $this->rows[$id] = $delivery;
        $this->contexts[$id] = $this->sourceName;
        $this->log = [];

        return $delivery;
    }
}

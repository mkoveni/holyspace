<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\TicketRepository;
use App\Events\Domain\TicketOrder;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Payments\Application\Contract\GuestAccess;
use App\Shared\Application\UnitOfWork;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Door scanning (design 5.9). A scan answers ADMITTED, ALREADY_USED or REJECTED and never throws for a bad code, so the
 * scanner screen always has something to show. Locks the occurrence, then the ticket, like every other ticket write.
 */
final readonly class TicketDoor
{
    public const string ADMITTED = 'ADMITTED';
    public const string ALREADY_USED = 'ALREADY_USED';
    public const string REJECTED = 'REJECTED';

    public function __construct(
        private TicketRepository $tickets,
        private GuestAccess $access,
        private PermissionChecker $permissions,
        private EventJournal $journal,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{result: string, reason: ?string, ticketType: ?string, holder: ?string, checkedInAt: ?DateTimeImmutable, counts: array{issued: int, checkedIn: int, void: int}}
     */
    public function scan(string $actorId, string $occurrenceId, string $code): array
    {
        $this->permissions->requireActorPermission($actorId, 'events.tickets.scan');

        return $this->transactions->execute(function () use ($actorId, $occurrenceId, $code): array {
            $occurrence = $this->tickets->lockOccurrence($occurrenceId) ?? throw EventException::missing('Occurrence');
            $now = $this->clock->now();
            $ticket = $this->find($occurrenceId, $code);
            if (null === $ticket) {
                return $this->answer(self::REJECTED, 'This is not a ticket for this event.', null, $occurrenceId);
            }
            if ('SCHEDULED' !== $occurrence->status) {
                return $this->answer(self::REJECTED, 'This event is not running.', $ticket, $occurrenceId);
            }
            if ('VOID' === $ticket['status'] || !\in_array($ticket['orderStatus'], TicketOrder::HOLDING, true)) {
                return $this->answer(self::REJECTED, 'This ticket was refunded or cancelled.', $ticket, $occurrenceId);
            }
            if ('CHECKED_IN' === $ticket['status']) {
                return $this->answer(self::ALREADY_USED, 'This ticket was already scanned.', $ticket, $occurrenceId);
            }
            $this->tickets->checkIn($ticket['id'], $now);
            $this->journal->audit($actorId, 'tickets.scan.admitted', $ticket['id'], 'Ticket scanned at the door.', $now);

            return $this->answer(self::ADMITTED, null, ['checkedInAt' => $now] + $ticket, $occurrenceId);
        });
    }

    /** @return array{id: string, orderId: string, typeName: string, holder: string, status: string, checkedInAt: ?DateTimeImmutable, orderStatus: string}|null */
    private function find(string $occurrenceId, string $code): ?array
    {
        $parts = explode('.', trim($code), 2);
        if (2 !== \count($parts) || 1 !== preg_match('/^[0-9a-fA-F-]{36}$/', $parts[0]) || '' === $parts[1] || \strlen($parts[1]) > 200) {
            return null;
        }
        $ticket = $this->tickets->lockTicketByCode($occurrenceId, $this->access->hash($parts[1]));

        return null !== $ticket && strtolower($ticket['id']) === strtolower($parts[0]) ? $ticket : null;
    }

    /**
     * @param array{typeName: string, holder: string, checkedInAt: ?DateTimeImmutable}|null $ticket
     *
     * @return array{result: string, reason: ?string, ticketType: ?string, holder: ?string, checkedInAt: ?DateTimeImmutable, counts: array{issued: int, checkedIn: int, void: int}}
     */
    private function answer(string $result, ?string $reason, ?array $ticket, string $occurrenceId): array
    {
        return [
            'result' => $result,
            'reason' => $reason,
            'ticketType' => $ticket['typeName'] ?? null,
            'holder' => $ticket['holder'] ?? null,
            'checkedInAt' => $ticket['checkedInAt'] ?? null,
            'counts' => $this->tickets->scanCounts($occurrenceId),
        ];
    }
}

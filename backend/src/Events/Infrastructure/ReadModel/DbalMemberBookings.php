<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\ReadModel;

use App\Events\Application\Command\CancelEventRegistration\CancelEventRegistrationCommand;
use App\Events\Application\Command\CancelEventRegistration\CancelEventRegistrationHandler;
use App\Events\Application\Port\MemberBookingCancellation;
use App\Events\Application\ReadModel\MemberBookings;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Events\Domain\Repository\EventRegistrationRepository;
use App\Shared\Application\WorkflowTransaction;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use OutOfBoundsException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(MemberBookings::class)]
#[AsAlias(MemberBookingCancellation::class)]
final readonly class DbalMemberBookings implements MemberBookingCancellation
{
    public function __construct(
        private Connection $db,
        private EventRegistrationRepository $records,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private CancelEventRegistrationHandler $cancelEventRegistrationHandler,
    ) {
    }

    public function list(string $personId): array
    {
        $items = [];
        $rows = $this->db->fetchAllAssociative('SELECT r.id, r.status, r.version, o.id AS occurrence_id, o.event_id, o.starts_at, o.ends_at, o.status AS occurrence_status, o.location, e.title, e.timezone
             FROM event_registrations r
             LEFT JOIN church_event_occurrences o ON o.id = r.occurrence_id
             LEFT JOIN church_events e ON e.id = o.event_id
             WHERE r.person_id = ? ORDER BY r.queue_token DESC LIMIT 100', [$personId]);
        foreach ($rows as $row) {
            $occurrence = null === $row['occurrence_id'] || null === $row['title'] ? null : ['id' => $row['occurrence_id'], 'eventId' => $row['event_id'], 'title' => $row['title'], 'startsAt' => null === $row['starts_at'] ? null : (new DateTimeImmutable($row['starts_at']))->format(DATE_ATOM), 'endsAt' => null === $row['ends_at'] ? null : (new DateTimeImmutable($row['ends_at']))->format(DATE_ATOM), 'status' => $row['occurrence_status'], 'timezone' => $row['timezone'], 'location' => $row['location']];
            $items[] = ['id' => $row['id'], 'status' => $row['status'], 'version' => (int) $row['version'], 'occurrence' => $occurrence];
        }

        return $items;
    }

    public function cancel(string $personId, string $registrationId, int $expectedVersion): array
    {
        return $this->transactions->run(function () use ($personId, $registrationId, $expectedVersion) {
            $record = $this->records->find($registrationId);
            if (!$record || $record->personId !== $personId) {
                throw new OutOfBoundsException('Registration not found.');
            }
            $occurrence = $this->occurrences->get($record->occurrenceId) ?? throw new OutOfBoundsException('Occurrence not found.');
            $result = ($this->cancelEventRegistrationHandler)(new CancelEventRegistrationCommand($occurrence['eventId'], $record->occurrenceId, $record->id, 'Cancelled by the registered member.', $expectedVersion));

            return ['id' => $result->id, 'status' => $result->status, 'version' => $result->version];
        });
    }
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Infrastructure\ReadModel;

use App\ChildrenMinistry\Application\ReadModel\ChildAttendanceDirectory;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ChildAttendanceDirectory::class)]
final readonly class MinistryAttendanceDirectory implements ChildAttendanceDirectory
{
    public function __construct(private ChildrenMinistryRepository $records)
    {
    }

    public function all(): array
    {
        $result = [];
        $occurrencesBySession = [];
        foreach ($this->records->all('sessions') as $session) {
            $occurrencesBySession[$session->id] = $session->occurrenceId;
        }
        foreach ($this->records->all('checkins') as $checkin) {
            $occurrenceId = $occurrencesBySession[$checkin->sessionId] ?? null;
            if (null === $occurrenceId) {
                continue;
            }
            $id = $occurrenceId.':'.$checkin->childId;
            $result[$id] = ['personId' => $checkin->childId, 'occurrenceId' => $occurrenceId, 'status' => 'present', 'source' => 'children', 'id' => $id];
        }

        return array_values($result);
    }

    public function forOccurrence(string $occurrenceId): array
    {
        $sessions = [];
        foreach ($this->records->all('sessions') as $session) {
            if ($session->occurrenceId === $occurrenceId) {
                $sessions[$session->id] = true;
            }
        }
        $result = [];
        foreach ($this->records->all('checkins') as $checkin) {
            if (isset($sessions[$checkin->sessionId])) {
                $result[$checkin->childId] = ['personId' => $checkin->childId, 'occurrenceId' => $occurrenceId, 'status' => 'present', 'source' => 'children'];
            }
        }

        return array_values($result);
    }
}

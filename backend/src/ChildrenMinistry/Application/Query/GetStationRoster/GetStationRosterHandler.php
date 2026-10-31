<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Query\GetStationRoster;

use App\ChildrenMinistry\Application\ReadModel\ChildrenMinistryReadStore;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;

final readonly class GetStationRosterHandler
{
    public function __construct(private ChildrenMinistryReadStore $records, private PersonDirectory $people, private OccurrenceDirectory $occurrences, private AuthenticatedAccount $tokens, private AccessControl $access, private ClockInterface $clock)
    {
    }

    public function __invoke(GetStationRosterQuery $message): array
    {
        $id = $message->id;
        $session = $this->session($id);
        $roster = [];
        foreach ($this->records->all('enrolments') as $enrolment) {
            if ($enrolment->classId === $session->classId && $enrolment->active && $enrolment->enrolledOn <= $this->clock->now()->format('Y-m-d')) {
                $roster[$enrolment->childId] = true;
            }
        }
        $checkins = [];
        foreach ($this->records->all('checkins') as $checkin) {
            if ($checkin->sessionId === $id && null === $checkin->checkedOutAt) {
                $roster[$checkin->childId] = true;
                $checkins[$checkin->childId] = $checkin;
            }
        }
        $items = [];
        $authorisations = $this->records->all('authorisations');
        $people = $this->people->getMany([...array_keys($roster), ...array_column($authorisations, 'collectorId')]);
        foreach ($roster as $childId => $_) {
            $collectors = [];
            foreach ($authorisations as $a) {
                if ($a->childId === $childId && $a->active && $a->validUntil >= $this->clock->now()->format('Y-m-d')) {
                    $collectors[] = ['id' => $a->collectorId, 'name' => $people[$a->collectorId]['name'] ?? 'Unavailable'];
                }
            }
            $items[] = ['id' => $childId, 'name' => $people[$childId]['name'] ?? 'Unavailable', 'checkIn' => $checkins[$childId] ?? null, 'collectors' => $collectors];
        }

        return ['session' => $session, 'className' => $this->required('classes', $session->classId)->name, 'occurrence' => $this->occurrences->get($session->occurrenceId), 'items' => $items];
    }

    private function session(string $id): object
    {
        $operator = $this->operator();
        $session = $this->required('sessions', $id);
        if (!in_array($operator, $session->staffPersonIds, true)) {
            throw new AccessDenied('This session is not assigned to you.');
        }

        return $session;
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }

    private function operator(): string
    {
        $user = $this->tokens->user();
        if (!$this->access->isGranted('children.station') || null === $user || 'active' !== $user->status()->value || !$user->personId()) {
            throw new AccessDenied('An active account linked to supervising staff is required.');
        }

        return $user->personId();
    }
}

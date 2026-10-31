<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetOwnVolunteerSchedule;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Volunteering\Application\ReadModel\VolunteeringReadStore;
use OutOfBoundsException;

final readonly class GetOwnVolunteerScheduleHandler
{
    public function __construct(private VolunteeringReadStore $records, private OccurrenceDirectory $occurrences, private AuthenticatedAccount $tokens, private AccessControl $authorization)
    {
    }

    public function __invoke(GetOwnVolunteerScheduleQuery $message): array
    {
        $personId = $this->person();
        $items = [];
        $assignments = array_filter($this->records->all('assignments'), static fn ($assignment) => $assignment->personId === $personId);
        $shifts = array_column($this->records->all('shifts'), null, 'id');
        $roles = array_column($this->records->all('roles'), null, 'id');
        $occurrences = $this->occurrences->getMany(array_values(array_unique(array_map(static fn ($a) => ($shifts[$a->shiftId] ?? throw new OutOfBoundsException('Record not found.'))->occurrenceId, $assignments))));
        $invitationsByAssignment = [];
        foreach ($this->records->all('invitations') as $invitation) {
            $invitationsByAssignment[$invitation->assignmentId][] = $invitation;
        }
        foreach ($assignments as $a) {
            $shift = $shifts[$a->shiftId] ?? throw new OutOfBoundsException('Record not found.');
            $role = $roles[$shift->roleId] ?? throw new OutOfBoundsException('Record not found.');
            $items[] = ['assignment' => $a, 'roleName' => $role->name, 'occurrence' => $occurrences[$shift->occurrenceId] ?? null, 'startsAt' => $shift->startsAt, 'endsAt' => $shift->endsAt, 'invitations' => $invitationsByAssignment[$a->id] ?? []];
        }

        return ['items' => $items];
    }

    private function person(): string
    {
        $user = $this->tokens->user();
        if (!$this->authorization->isGranted('volunteering.serve') || null === $user || 'active' !== $user->status()->value || !$user->personId()) {
            throw new AccessDenied('An active volunteer account linked to your person record is required.');
        }

        return $user->personId();
    }
}

<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Query\ListStationSessions;

use App\ChildrenMinistry\Application\ReadModel\ChildrenMinistryReadStore;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use OutOfBoundsException;

final readonly class ListStationSessionsHandler
{
    public function __construct(private ChildrenMinistryReadStore $records, private OccurrenceDirectory $occurrences, private AuthenticatedAccount $tokens, private AccessControl $access)
    {
    }

    public function __invoke(ListStationSessionsQuery $message): array
    {
        $operator = $this->operator();
        $items = [];
        $sessions = array_filter($this->records->all('sessions'), static fn ($session) => in_array($operator, $session->staffPersonIds, true));
        $classes = array_column($this->records->all('classes'), null, 'id');
        $occurrences = $this->occurrences->getMany(array_column($sessions, 'occurrenceId'));
        foreach ($sessions as $session) {
            $class = $classes[$session->classId] ?? throw new OutOfBoundsException('Record not found.');
            $items[] = ['id' => $session->id, 'status' => $session->status, 'title' => $class->name, 'occurrence' => $occurrences[$session->occurrenceId] ?? null];
        }

        return ['items' => $items];
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

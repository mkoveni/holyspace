<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Query\GetChildSupport;

use App\ChildrenMinistry\Application\Query\GetStationRoster\GetStationRosterHandler;
use App\ChildrenMinistry\Application\Query\GetStationRoster\GetStationRosterQuery;
use App\ChildrenMinistry\Application\ReadModel\ChildrenMinistryReadStore;
use App\ChildrenMinistry\Domain\Model\ChildSupportNote;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;

final readonly class GetChildSupportHandler
{
    public function __construct(
        private ChildrenMinistryReadStore $records,
        private AccessControl $access,
        private GetStationRosterHandler $getStationRosterHandler,
    ) {
    }

    public function __invoke(GetChildSupportQuery $message): array
    {
        $sessionId = $message->sessionId;
        $childId = $message->childId;
        $roster = ($this->getStationRosterHandler)(new GetStationRosterQuery($sessionId));
        if (!$this->access->isGranted('children.support')) {
            throw new AccessDenied('Support note permission is required.');
        }
        if (!in_array($childId, array_column($roster['items'], 'id'), true)) {
            throw new AccessDenied('Child is outside this session.');
        }
        $note = $this->note($childId);

        return ['note' => $note?->note ?? '', 'version' => $note?->version ?? 0];
    }

    private function note(string $id): ?ChildSupportNote
    {
        foreach ($this->records->all('supportnotes') as $note) {
            if ($note->childId === $id) {
                return $note;
            }
        }

        return null;
    }
}

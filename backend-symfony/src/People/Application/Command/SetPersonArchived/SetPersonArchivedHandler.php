<?php

declare(strict_types=1);

namespace App\People\Application\Command\SetPersonArchived;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\PersonRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class SetPersonArchivedHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private PersonRepository $people,
    ) {
    }

    public function handle(SetPersonArchivedCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');

        $person = $this->people->findForUpdate($command->personId) ?? throw PeopleException::missing('Person');
        $now = $this->clock->now();
        $this->people->update($person->archive($command->archived, $command->expectedVersion, $now));
        $this->audit->audit($command->actorId, $command->archived ? 'person.archived' : 'person.restored', $person->id, $now);
    }
}

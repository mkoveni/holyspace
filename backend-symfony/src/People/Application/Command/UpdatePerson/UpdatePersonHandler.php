<?php

declare(strict_types=1);

namespace App\People\Application\Command\UpdatePerson;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Application\Contract\TitleReader;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\PersonRepository;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class UpdatePersonHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private PersonRepository $people,
        private TitleReader $titles,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(UpdatePersonCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');

        $person = $this->people->findForUpdate($command->personId) ?? throw PeopleException::missing('Person');
        $now = $this->clock->now();
        $titleCode = $command->details->titleCode;
        if (null !== $titleCode && !$this->titles->exists($titleCode)) {
            throw PeopleException::invalid('Select an existing title.');
        }
        $this->people->update($person->update($command->details, $command->membershipType, $command->expectedVersion, $this->calendar->today(), $now));
        $this->audit->audit($command->actorId, 'person.updated', $person->id, $now);
    }
}

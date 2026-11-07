<?php

declare(strict_types=1);

namespace App\People\Application\Command\RegisterPerson;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Application\Contract\TitleReader;
use App\People\Domain\PeopleException;
use App\People\Domain\Person;
use App\People\Domain\Repository\PersonRepository;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class RegisterPersonHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private PersonRepository $people,
        private TitleReader $titles,
        private ChurchCalendar $calendar,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the new person id */
    public function handle(RegisterPersonCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');

        $now = $this->clock->now();
        $titleCode = $command->details->titleCode;
        if (null !== $titleCode && !$this->titles->exists($titleCode)) {
            throw PeopleException::invalid('Select an existing title.');
        }
        $person = Person::register($this->ids->uuid(), $command->details, $command->membershipType, $this->calendar->today(), $now);
        $this->people->insert($person);
        $this->audit->audit($command->actorId, 'person.registered', $person->id, $now);

        return $person->id;
    }
}

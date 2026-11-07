<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddContact;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Domain\ContactMethod;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\PersonRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class AddContactHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private PersonRepository $people,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the new contact id */
    public function handle(AddContactCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');

        $person = $this->people->findForUpdate($command->personId) ?? throw PeopleException::missing('Person');
        $now = $this->clock->now();
        $person->requireAvailable();
        $this->people->update($person->touch($command->expectedVersion, $now));
        $contact = ContactMethod::add($this->ids->uuid(), $person->id, $command->kind, $command->value, $command->primary, $now);
        $this->people->addContact($contact);
        $this->audit->audit($command->actorId, 'person.contact.added', $person->id, $now);

        return $contact->id;
    }
}

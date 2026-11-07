<?php

declare(strict_types=1);

namespace App\People\Application\Command\SaveMyContact;

use App\IdentityAccess\Application\Contract\MemberIdentity;
use App\IdentityAccess\Application\Contract\MemberIdentityGuard;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Application\Contract\PersonReader;
use App\People\Domain\ContactMethod;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\PersonRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * A verified member adds a contact method, optionally replacing one of their own of the same kind. The old entry
 * is ended, never erased, and the new one becomes the primary contact of its kind.
 */
final readonly class SaveMyContactHandler implements CommandHandler
{
    public function __construct(
        private MemberIdentity $identity,
        private MemberIdentityGuard $guard,
        private PersonRepository $people,
        private PersonReader $reader,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the new contact id */
    public function handle(SaveMyContactCommand $command): string
    {
        $this->identity->requirePerson($command->actorId);

        $person = $this->people->findForUpdate($this->guard->lockPerson($command->actorId)) ?? throw PeopleException::missing('Person');
        $this->identity->requirePerson($command->actorId);
        $person->requireAvailable();

        $now = $this->clock->now();
        $added = ContactMethod::add(
            $this->ids->uuid(),
            $person->id,
            $command->kind ?? throw PeopleException::invalid('Contact kind is required.'),
            $command->value,
            true,
            $now,
        );
        if (null !== $command->contactId) {
            $replaced = $this->reader->activeContact($person->id, $command->contactId);
            if (null === $replaced || $replaced->kind !== $added->kind) {
                throw PeopleException::missing('Contact');
            }
            $this->people->endContact($person->id, $command->contactId, $now);
        }
        $this->people->update($person->touch($command->expectedVersion, $now));
        $this->people->addContact($added);
        $this->audit->audit($command->actorId, 'person.contact.self_saved', $person->id, $now);

        return $added->id;
    }
}

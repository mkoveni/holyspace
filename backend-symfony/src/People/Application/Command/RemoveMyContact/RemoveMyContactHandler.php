<?php

declare(strict_types=1);

namespace App\People\Application\Command\RemoveMyContact;

use App\IdentityAccess\Application\Contract\MemberIdentity;
use App\IdentityAccess\Application\Contract\MemberIdentityGuard;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Application\Contract\PersonReader;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\PersonRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** A verified member ends one of their own active contact methods. Someone else's contact is simply not found. */
final readonly class RemoveMyContactHandler implements CommandHandler
{
    public function __construct(
        private MemberIdentity $identity,
        private MemberIdentityGuard $guard,
        private PersonRepository $people,
        private PersonReader $reader,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RemoveMyContactCommand $command): void
    {
        $this->identity->requirePerson($command->actorId);

        $person = $this->people->findForUpdate($this->guard->lockPerson($command->actorId)) ?? throw PeopleException::missing('Person');
        $this->identity->requirePerson($command->actorId);
        $person->requireAvailable();
        if (null === $this->reader->activeContact($person->id, $command->contactId)) {
            throw PeopleException::missing('Contact');
        }

        $now = $this->clock->now();
        $this->people->update($person->touch($command->expectedVersion, $now));
        $this->people->endContact($person->id, $command->contactId, $now);
        $this->audit->audit($command->actorId, 'person.contact.self_removed', $person->id, $now);
    }
}

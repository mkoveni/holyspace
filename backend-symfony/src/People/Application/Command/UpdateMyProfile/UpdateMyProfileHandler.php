<?php

declare(strict_types=1);

namespace App\People\Application\Command\UpdateMyProfile;

use App\IdentityAccess\Application\Contract\MemberIdentity;
use App\IdentityAccess\Application\Contract\MemberIdentityGuard;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Application\Contract\TitleReader;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\PersonRepository;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * A verified member edits their own name, title and gender. Date of birth, life status and membership type stay
 * staff-managed, and the person is always the caller's verified one, never a value from the request.
 */
final readonly class UpdateMyProfileHandler implements CommandHandler
{
    public function __construct(
        private MemberIdentity $identity,
        private MemberIdentityGuard $guard,
        private PersonRepository $people,
        private TitleReader $titles,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(UpdateMyProfileCommand $command): void
    {
        $this->identity->requirePerson($command->actorId);

        $person = $this->people->findForUpdate($this->guard->lockPerson($command->actorId)) ?? throw PeopleException::missing('Person');
        $this->identity->requirePerson($command->actorId);
        $person->requireAvailable();
        if (null !== $command->titleCode && !$this->titles->exists($command->titleCode)) {
            throw PeopleException::invalid('Select an existing title.');
        }

        $now = $this->clock->now();
        $details = $person->details->withProfile($command->titleCode, $command->firstName, $command->lastName, $command->gender);
        $this->people->update($person->update($details, $person->membershipType, $command->expectedVersion, $this->calendar->today(), $now));
        $this->audit->audit($command->actorId, 'person.self_updated', $person->id, $now);
    }
}

<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddAddress;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Domain\PeopleException;
use App\People\Domain\PersonAddress;
use App\People\Domain\Repository\PersonRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class AddAddressHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private PersonRepository $people,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the new address id */
    public function handle(AddAddressCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');

        $person = $this->people->findForUpdate($command->personId) ?? throw PeopleException::missing('Person');
        $now = $this->clock->now();
        $person->requireAvailable();
        $this->people->update($person->touch($command->expectedVersion, $now));
        $address = PersonAddress::add(
            $this->ids->uuid(),
            $person->id,
            $command->line1,
            $command->line2,
            $command->city,
            $command->region,
            $command->postalCode,
            $command->countryCode,
            $now,
        );
        $this->people->addAddress($address);
        $this->audit->audit($command->actorId, 'person.address.added', $person->id, $now);

        return $address->id;
    }
}

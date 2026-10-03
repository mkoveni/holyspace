<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddAddress;

use App\People\Domain\Model\Address;
use App\People\Domain\Repository\PersonProfileRepository;
use App\People\Domain\ValueObject\AddressId;
use App\People\Domain\ValueObject\PersonId;
use App\People\Domain\Enum\AddressType;

#[\Symfony\Component\Messenger\Attribute\AsMessageHandler]
final readonly class AddAddressHandler
{
    public function __construct(private PersonProfileRepository $repo) {}

    public function __invoke(AddAddressCommand $c): AddressId
    {
        $id = AddressId::generate();
        $this->repo->addAddress(PersonId::fromString($c->personId), Address::create(
            $id,
            AddressType::from($c->type),
            $c->line1,
            $c->line2,
            $c->city,
            $c->province,
            $c->postalCode,
            $c->country
        ));
        return $id;
    }
}

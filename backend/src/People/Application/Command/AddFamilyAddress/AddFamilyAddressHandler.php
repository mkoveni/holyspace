<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddFamilyAddress;

use App\People\Domain\Enum\AddressType;
use App\People\Domain\Model\Address;
use App\People\Domain\Repository\PersonProfileRepository;
use App\People\Domain\ValueObject\AddressId;
use App\People\Domain\ValueObject\FamilyId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddFamilyAddressHandler
{
    public function __construct(private PersonProfileRepository $repo)
    {
    }

    public function __invoke(AddFamilyAddressCommand $addFamilyAddressCommand): AddressId
    {
        $id = AddressId::generate();
        $this->repo->addFamilyAddress(FamilyId::fromString($addFamilyAddressCommand->familyId), Address::create($id, AddressType::from($addFamilyAddressCommand->type), $addFamilyAddressCommand->line1, $addFamilyAddressCommand->line2, $addFamilyAddressCommand->city, $addFamilyAddressCommand->province, $addFamilyAddressCommand->postalCode, $addFamilyAddressCommand->country));

        return $id;
    }
}

<?php

declare(strict_types=1);

namespace App\People\Application\Command\SetFamilyPrimaryContact;

use App\People\Domain\Repository\FamilyRepository;
use App\People\Domain\ValueObject\FamilyId;
use App\People\Domain\ValueObject\PersonId;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetFamilyPrimaryContactHandler
{
    public function __construct(private FamilyRepository $families)
    {
    }

    public function __invoke(SetFamilyPrimaryContactCommand $command): void
    {
        $family = $this->families->findById(FamilyId::fromString($command->familyId));
        if (null === $family) {
            throw new RuntimeException('Family not found.');
        }
        $family->setPrimaryContact(null === $command->personId ? null : PersonId::fromString($command->personId));
        $this->families->save($family);
    }
}

<?php

declare(strict_types=1);

namespace App\People\Infrastructure;

use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonDirectoryEntry;
use App\People\Application\Contract\PersonReader;
use App\People\Domain\Repository\PersonRepository;
use App\People\Domain\ValueObject\LifeStatus;

/** What other bounded contexts may know about a person: a name, a birth date and whether they are still available. */
final readonly class PublishedPersonDirectory implements PersonDirectory
{
    public function __construct(private PersonReader $reader, private PersonRepository $repository)
    {
    }

    public function getAll(array $ids): array
    {
        return $this->reader->entries($ids);
    }

    public function matchingIds(string $search): array
    {
        return $this->reader->matchingIds($search);
    }

    public function get(string $id): ?PersonDirectoryEntry
    {
        return $this->reader->entries([$id])[0] ?? null;
    }

    public function lock(string $id): ?PersonDirectoryEntry
    {
        $person = $this->repository->findForUpdate($id);
        if (null === $person) {
            return null;
        }

        return new PersonDirectoryEntry(
            $person->id,
            $person->details->firstName.' '.$person->details->lastName,
            $person->details->dateOfBirth,
            null === $person->archivedAt && LifeStatus::Deceased !== $person->details->lifeStatus,
        );
    }
}

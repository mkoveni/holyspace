<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

/** Published to other bounded contexts so they never read People tables or aggregates directly. */
interface PersonDirectory
{
    /**
     * @param list<string> $ids
     *
     * @return list<PersonDirectoryEntry>
     */
    public function getAll(array $ids): array;

    /** @return list<string> */
    public function matchingIds(string $search): array;

    public function get(string $id): ?PersonDirectoryEntry;

    /** Like get, but holds a row lock until the surrounding transaction ends. */
    public function lock(string $id): ?PersonDirectoryEntry;
}

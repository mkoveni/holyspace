<?php

declare(strict_types=1);

namespace App\Events\Application\ReadModel;

interface OccurrenceDirectory
{
    public function get(string $id): ?array;

    /** @param list<string> $ids
     * @return array<string, array>
     */
    public function getMany(array $ids): array;
}

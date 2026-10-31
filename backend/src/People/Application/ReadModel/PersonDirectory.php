<?php

declare(strict_types=1);

namespace App\People\Application\ReadModel;

interface PersonDirectory
{
    public function get(string $id): ?array;

    /** @param list<string> $ids
     * @return array<string, array{id: string, name: string, dateOfBirth: ?string, email: ?string, phone: ?string}>
     */
    public function getMany(array $ids): array;
}

<?php

declare(strict_types=1);

namespace App\People\Application\ReadModel;

use App\People\Application\DTO\PersonSearchResult;

interface PersonSearchReader
{
    public function search(string $query, int $limit = 20, array $excludeIds = [], array $ids = []): PersonSearchResult;
}

<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\ReadModel;

interface AccessCatalogue
{
    public function permissions(): array;

    public function roles(): array;

    public function accounts(string $search = '', int $limit = 50): array;
}

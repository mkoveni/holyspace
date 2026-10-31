<?php

declare(strict_types=1);

namespace App\People\Application\ReadModel;

interface MemberPersonalRecords
{
    public function profile(string $personId): array;

    public function families(string $personId): array;
}

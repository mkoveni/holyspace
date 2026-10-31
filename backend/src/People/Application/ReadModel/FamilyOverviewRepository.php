<?php

declare(strict_types=1);

namespace App\People\Application\ReadModel;

interface FamilyOverviewRepository
{
    /** @return list<\App\People\Application\DTO\FamilyOverviewView> */
    public function overview(?string $familyId = null): array;
}

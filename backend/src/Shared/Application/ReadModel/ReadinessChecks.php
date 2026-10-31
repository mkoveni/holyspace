<?php

declare(strict_types=1);

namespace App\Shared\Application\ReadModel;

interface ReadinessChecks
{
    public function checks(): array;
}

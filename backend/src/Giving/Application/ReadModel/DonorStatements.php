<?php

declare(strict_types=1);

namespace App\Giving\Application\ReadModel;

interface DonorStatements
{
    public function statement(string $donorId, string $from, string $to): array;
}

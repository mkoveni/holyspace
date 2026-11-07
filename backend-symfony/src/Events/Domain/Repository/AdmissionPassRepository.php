<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\AdmissionPass;
use DateTimeImmutable;

interface AdmissionPassRepository
{
    public function insert(AdmissionPass $pass): void;

    public function find(string $tokenHash): ?AdmissionPass;

    public function lock(string $tokenHash): ?AdmissionPass;

    public function markUsed(string $tokenHash, DateTimeImmutable $at): void;
}

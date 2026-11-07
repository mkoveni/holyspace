<?php

declare(strict_types=1);

namespace App\Communications\Domain\Repository;

use App\Communications\Domain\PushDevice;

interface PushDeviceRepository
{
    /**
     * Inserts the draft unless its id exists, first retiring the same account's other active registration of the token.
     * Throws a conflict when the token is active on another account's registration.
     */
    public function ensure(PushDevice $draft): void;

    public function lock(string $id): ?PushDevice;

    public function save(PushDevice $device): void;
}

<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Application\IdGenerator;
use Symfony\Component\Uid\Uuid;

final class SymfonyIdGenerator implements IdGenerator
{
    public function uuid(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}

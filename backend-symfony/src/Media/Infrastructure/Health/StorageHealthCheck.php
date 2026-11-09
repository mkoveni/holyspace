<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Health;

use App\Media\Infrastructure\LocalFileStore;
use App\Shared\Application\HealthCheck;

/** Pictures cannot be saved unless the storage directory exists and is writable. */
final readonly class StorageHealthCheck implements HealthCheck
{
    public function __construct(private LocalFileStore $store)
    {
    }

    public function healthy(): bool
    {
        return $this->store->writable();
    }
}

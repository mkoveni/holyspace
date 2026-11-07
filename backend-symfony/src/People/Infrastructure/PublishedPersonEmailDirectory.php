<?php

declare(strict_types=1);

namespace App\People\Infrastructure;

use App\People\Application\Contract\PersonEmailDirectory;
use App\People\Application\Contract\PersonReader;

final readonly class PublishedPersonEmailDirectory implements PersonEmailDirectory
{
    public function __construct(private PersonReader $reader)
    {
    }

    public function active(string $personId): array
    {
        return $this->reader->activeEmails($personId);
    }
}

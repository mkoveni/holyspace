<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\People\Application\Contract\PersonEmail;
use App\People\Application\Contract\PersonEmailDirectory;

final readonly class OneEmail implements PersonEmailDirectory
{
    public function __construct(private string $contactId)
    {
    }

    public function active(string $personId): array
    {
        return [new PersonEmail($this->contactId, 'member@example.org')];
    }
}

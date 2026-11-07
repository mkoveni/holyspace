<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

interface PersonEmailDirectory
{
    /** @return list<PersonEmail> active email addresses of the person */
    public function active(string $personId): array;
}

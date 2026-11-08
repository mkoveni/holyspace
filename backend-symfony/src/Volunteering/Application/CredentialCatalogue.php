<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Volunteering\Application\Contract\VolunteerReader;

/** Whether a credential type id is in the catalogue. */
final readonly class CredentialCatalogue
{
    public function __construct(private VolunteerReader $reader)
    {
    }

    public function knows(?string $typeId): bool
    {
        foreach ($this->reader->credentialTypes() as $type) {
            if ($type->id === $typeId) {
                return true;
            }
        }

        return false;
    }
}

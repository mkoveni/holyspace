<?php

declare(strict_types=1);

namespace App\People\Application\Command\SetFamilyPrimaryContact;

use App\Shared\Application\CQRS\Command;

final readonly class SetFamilyPrimaryContactCommand implements Command
{
    public function __construct(public string $familyId, public ?string $personId)
    {
    }
}

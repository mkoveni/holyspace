<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SaveVolunteerShift;

use App\Shared\Application\CQRS\Command;

final readonly class SaveVolunteerShiftCommand implements Command
{
    public function __construct(public array $data, public ?string $id = null)
    {
    }
}

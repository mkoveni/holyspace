<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SaveVolunteerRole;

use App\Shared\Application\CQRS\Command;

final readonly class SaveVolunteerRoleCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}

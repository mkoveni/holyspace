<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\AssignVolunteer;

use App\Shared\Application\CQRS\Command;

final readonly class AssignVolunteerCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}

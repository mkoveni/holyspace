<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddEducation;

use App\Shared\Application\CQRS\Command;

final readonly class AddEducationCommand implements Command
{
    public function __construct(
        public string $personId,
        public string $institution,
        public string $qualification,
        public ?string $startDate,
        public ?string $endDate,
        public string $fieldOfStudy,
        public string $notes,
    ) {
    }
}

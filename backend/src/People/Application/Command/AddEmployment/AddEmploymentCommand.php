<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddEmployment;

use App\Shared\Application\CQRS\Command;

final readonly class AddEmploymentCommand implements Command
{
    public function __construct(
        public string $personId,
        public string $employer,
        public string $jobTitle,
        public ?string $startDate,
        public ?string $endDate,
        public string $industry,
        public string $workEmail,
        public string $workPhone,
        public string $notes,
    ) {
    }
}

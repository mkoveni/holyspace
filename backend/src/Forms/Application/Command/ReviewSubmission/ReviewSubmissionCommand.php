<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\ReviewSubmission;

use App\Shared\Application\CQRS\Command;

final readonly class ReviewSubmissionCommand implements Command
{
    public function __construct(
        public string $id,
        public string $status,
        public string $note,
        public ?string $personId,
        public int $version,
    ) {
    }
}

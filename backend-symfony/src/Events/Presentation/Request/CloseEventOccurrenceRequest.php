<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CloseEventOccurrenceRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotBlank]
        public string $reviewHash,
        #[Assert\NotBlank]
        #[Assert\Length(max: 2000)]
        public string $note,
        #[Assert\Length(max: 2000)]
        public string $followUp = '',
        public bool $attendanceReviewed = false,
        public bool $followUpReviewed = false,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReviewVisitorRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['CONFIRM', 'REMOVE'])]
        public string $decision,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public string $reason,
        #[Assert\PositiveOrZero]
        public ?int $expectedAttendanceVersion = null,
    ) {
    }
}

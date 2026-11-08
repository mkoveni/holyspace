<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteerRemindersApi.Approval`. */
final readonly class ReminderApprovalRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $contactId = null,
        #[Assert\NotBlank, Assert\Length(max: 2000)]
        public ?string $message = null,
        #[Assert\NotBlank]
        public ?string $reviewHash = null,
        #[Assert\NotBlank, Assert\Length(max: 500)]
        public ?string $verificationEvidence = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteerRemindersApi.Draft`. */
final readonly class ReminderDraftRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $contactId = null,
        #[Assert\NotBlank, Assert\Length(max: 2000)]
        public ?string $message = null,
    ) {
    }
}

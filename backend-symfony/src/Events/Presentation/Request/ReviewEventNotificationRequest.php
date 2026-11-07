<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReviewEventNotificationRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $contactId = null,
        #[Assert\NotBlank]
        public string $purpose = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 2000)]
        public string $message = '',
    ) {
    }

    /** The message stays out of logs. */
    public function __debugInfo(): array
    {
        return ['contactId' => $this->contactId];
    }
}

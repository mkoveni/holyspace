<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ApproveEventNotificationRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $contactId = null,
        #[Assert\NotBlank]
        public string $purpose = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 2000)]
        public string $message = '',
        #[Assert\NotBlank]
        public string $reviewHash = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $verificationEvidence = '',
    ) {
    }

    /** Message and evidence stay out of logs. */
    public function __debugInfo(): array
    {
        return ['requestId' => $this->requestId];
    }
}

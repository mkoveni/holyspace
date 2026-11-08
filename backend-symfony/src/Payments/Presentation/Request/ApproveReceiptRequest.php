<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ApproveReceiptRequest
{
    public function __construct(
        #[Assert\NotNull] #[Assert\Uuid] public ?string $requestId = null,
        #[Assert\NotNull] #[Assert\Uuid] public ?string $chargeId = null,
        #[Assert\NotNull] #[Assert\Uuid] public ?string $contactId = null,
        #[Assert\NotBlank] #[Assert\Length(max: 64)] public ?string $reviewHash = null,
        #[Assert\NotBlank] #[Assert\Length(max: 500)] public ?string $evidence = null,
    ) {
    }

    /** @return array{redacted: true} */
    public function __debugInfo(): array
    {
        return ['redacted' => true];
    }
}

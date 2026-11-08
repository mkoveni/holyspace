<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `PaymentSettingsApi.SaveBody`; an omitted backendUrl keeps the saved backend origin. */
final readonly class SaveSettingsRequest
{
    public function __construct(
        #[Assert\PositiveOrZero] public int $expectedVersion,
        #[Assert\NotBlank] public ?string $mode = null,
        public bool $enabled = false,
        public bool $monthly = false,
        #[Assert\Length(max: 1000)] public ?string $publicUrl = null,
        #[Assert\Length(max: 2000)] public ?string $ipRanges = null,
        #[Assert\NotNull] #[Assert\Valid] public ?CredentialsRequest $credentials = null,
        #[Assert\NotBlank] #[Assert\Length(max: 1000)] public ?string $reason = null,
        #[Assert\Length(max: 1000)] public ?string $backendUrl = null,
    ) {
    }

    /** @return array{credentials: string} */
    public function __debugInfo(): array
    {
        return ['credentials' => 'redacted'];
    }
}

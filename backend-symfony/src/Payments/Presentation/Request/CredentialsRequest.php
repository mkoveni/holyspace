<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use App\Payments\Domain\ProviderCredentials;
use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;

/** Write-only replacement credentials; blank values keep the saved ones. */
final readonly class CredentialsRequest
{
    public function __construct(
        #[SensitiveParameter] #[Assert\Length(max: 1000)] public ?string $yocoKey = null,
        #[SensitiveParameter] #[Assert\Length(max: 1000)] public ?string $webhookSecret = null,
    ) {
    }

    public function credentials(): ProviderCredentials
    {
        return new ProviderCredentials($this->yocoKey, $this->webhookSecret);
    }

    /** @return array{redacted: true} */
    public function __debugInfo(): array
    {
        return ['redacted' => true];
    }
}

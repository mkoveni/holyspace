<?php

declare(strict_types=1);

namespace App\Communications\Presentation\Request;

use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PushDeviceRegistrationRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 100)]
        #[SensitiveParameter] public ?string $installationSecret = null,
        #[Assert\NotBlank, Assert\Length(max: 240)]
        #[SensitiveParameter] public ?string $token = null,
        #[Assert\NotBlank, Assert\Length(max: 10)]
        public ?string $platform = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $projectId = null,
    ) {
    }

    /** @return array<string, string|null> the secret and token are never dumped */
    public function __debugInfo(): array
    {
        return ['platform' => $this->platform, 'projectId' => $this->projectId];
    }
}

<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ScanMemberRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 250)]
        public string $credential = '',
    ) {
    }

    /** The credential stays out of logs. */
    public function __debugInfo(): array
    {
        return ['requestId' => $this->requestId];
    }
}

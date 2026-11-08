<?php

declare(strict_types=1);

namespace App\Communities\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ApproveCommunityNoticeRequest
{
    public function __construct(
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $contactId = null,
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 5000)]
        public ?string $message = null,
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 150)]
        public ?string $reviewHash = null,
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 500)]
        public ?string $evidence = null,
    ) {
    }
}

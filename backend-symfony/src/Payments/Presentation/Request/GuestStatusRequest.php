<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** The access token travels in the body, never in a URL, so it cannot reach logs or referrers. */
final readonly class GuestStatusRequest
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(max: 200)] public ?string $accessToken = null,
    ) {
    }
}

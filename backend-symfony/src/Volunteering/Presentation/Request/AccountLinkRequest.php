<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Java `VolunteerAccountApi.Link`: a null account unlinks. */
final readonly class AccountLinkRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\Uuid]
        public ?string $accountId = null,
    ) {
    }
}

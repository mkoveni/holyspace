<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PurgeExpiredSupportRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: 100)]
        public int $limit,
    ) {
    }
}

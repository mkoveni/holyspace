<?php

declare(strict_types=1);

namespace App\MemberPortal\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class StatementQuery
{
    public function __construct(
        #[Assert\Date] public string $from,
        #[Assert\Date] public string $to,
    ) {
    }
}

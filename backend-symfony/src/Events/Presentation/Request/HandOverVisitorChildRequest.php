<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class HandOverVisitorChildRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['HANDED_OVER', 'DISMISSED'])]
        public string $decision,
    ) {
    }
}

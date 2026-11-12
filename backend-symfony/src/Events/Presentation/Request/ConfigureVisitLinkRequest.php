<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConfigureVisitLinkRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['ENABLE', 'DISABLE', 'ROTATE', 'MESSAGES_ON', 'MESSAGES_OFF'])]
        public string $action,
    ) {
    }
}

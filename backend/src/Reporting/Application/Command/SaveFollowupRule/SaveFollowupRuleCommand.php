<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\SaveFollowupRule;

use App\Shared\Application\CQRS\Command;

final readonly class SaveFollowupRuleCommand implements Command
{
    public function __construct(
        public array $data,
        public ?string $id = null,
        public ?int $version = null,
    ) {
    }
}

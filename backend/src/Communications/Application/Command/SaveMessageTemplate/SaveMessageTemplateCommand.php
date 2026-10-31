<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\SaveMessageTemplate;

use App\Shared\Application\CQRS\Command;

final readonly class SaveMessageTemplateCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}

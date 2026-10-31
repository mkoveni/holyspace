<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\CreateForm;

use App\Shared\Application\CQRS\Command;

final readonly class CreateFormCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}

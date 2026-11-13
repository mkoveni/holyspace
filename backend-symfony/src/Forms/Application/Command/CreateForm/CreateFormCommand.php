<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\CreateForm;

use App\Forms\Application\FormDraft;
use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateFormCommand implements Command
{
    public function __construct(public string $actorId, public string $requestId, public FormDraft $draft)
    {
    }
}

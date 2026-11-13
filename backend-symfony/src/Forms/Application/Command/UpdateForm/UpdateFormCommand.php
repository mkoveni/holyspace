<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\UpdateForm;

use App\Forms\Application\FormDraft;
use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UpdateFormCommand implements Command
{
    public function __construct(public string $actorId, public string $formId, public int $expectedVersion, public FormDraft $draft)
    {
    }
}

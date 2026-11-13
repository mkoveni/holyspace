<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\ExportResponses;

use App\Shared\Application\Command;

/** @implements Command<ResponseExport> */
final readonly class ExportResponsesCommand implements Command
{
    public function __construct(public string $actorId, public string $formId)
    {
    }
}

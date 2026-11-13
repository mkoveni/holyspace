<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\ReadResponses;

use App\Shared\Application\Command;

/** @implements Command<ResponsePage> */
final readonly class ReadResponsesCommand implements Command
{
    public function __construct(public string $actorId, public string $formId, public int $limit, public int $offset)
    {
    }
}

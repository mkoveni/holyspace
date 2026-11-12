<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigureVisitLink;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ConfigureVisitLinkCommand implements Command
{
    public const string ENABLE = 'ENABLE';
    public const string DISABLE = 'DISABLE';
    public const string ROTATE = 'ROTATE';
    public const string MESSAGES_ON = 'MESSAGES_ON';
    public const string MESSAGES_OFF = 'MESSAGES_OFF';

    public function __construct(public string $actorId, public string $occurrenceId, public string $action)
    {
    }
}

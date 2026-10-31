<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\SaveCommunicationPreference;

use App\Shared\Application\CQRS\Command;

final readonly class SaveCommunicationPreferenceCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}

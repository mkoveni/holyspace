<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddCommunicationOption;

use App\Shared\Application\CQRS\Command;

final readonly class AddCommunicationOptionCommand implements Command
{
    public function __construct(
        public string $personId,
        public string $channel,
        public string $value,
        public bool $preferred = false,
    ) {
    }
}

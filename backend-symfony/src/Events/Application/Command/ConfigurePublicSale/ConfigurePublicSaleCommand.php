<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigurePublicSale;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<null> */
final readonly class ConfigurePublicSaleCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $occurrenceId,
        public int $expectedVersion,
        public bool $enabled,
        public ?DateTimeImmutable $opensAt,
        public ?DateTimeImmutable $closesAt,
        public int $maxPerOrder,
    ) {
    }
}

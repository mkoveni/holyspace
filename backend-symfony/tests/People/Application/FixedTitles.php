<?php

declare(strict_types=1);

namespace App\Tests\People\Application;

use App\People\Application\Contract\Title;
use App\People\Application\Contract\TitleReader;

/** In-memory test double: no database, no identity context. */
final class FixedTitles implements TitleReader
{
    /** @param list<string> $codes */
    public function __construct(private readonly array $codes)
    {
    }

    public function titles(): array
    {
        return array_map(static fn (string $code): Title => new Title($code, $code), $this->codes);
    }

    public function exists(string $code): bool
    {
        return in_array($code, $this->codes, true);
    }
}

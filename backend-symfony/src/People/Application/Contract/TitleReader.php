<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

interface TitleReader
{
    /** @return list<Title> ordered by label */
    public function titles(): array;

    public function exists(string $code): bool;
}

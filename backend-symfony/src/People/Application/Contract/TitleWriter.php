<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

interface TitleWriter
{
    public function add(string $code, string $label): void;
}

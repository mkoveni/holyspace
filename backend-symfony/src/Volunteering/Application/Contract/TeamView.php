<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

final readonly class TeamView
{
    public function __construct(public string $id, public bool $active)
    {
    }
}

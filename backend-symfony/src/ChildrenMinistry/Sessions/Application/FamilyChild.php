<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

final readonly class FamilyChild
{
    public function __construct(public string $id, public string $name)
    {
    }
}

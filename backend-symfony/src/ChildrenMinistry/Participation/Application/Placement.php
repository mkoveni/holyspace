<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application;

/** A class a child is currently placed in. */
final readonly class Placement
{
    public function __construct(public string $id, public string $name)
    {
    }
}

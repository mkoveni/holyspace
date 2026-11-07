<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application;

final readonly class LocationView
{
    public function __construct(public string $id, public string $name, public string $timeZone)
    {
    }
}

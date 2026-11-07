<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;

final readonly class Room
{
    public string $name;

    private function __construct(public string $id, public string $locationId, string $name, public int $capacity)
    {
        $this->name = ChildrenRules::text($name, 120);
        if ($capacity < 1) {
            throw ChildrenException::invalid('Room capacity must be positive.');
        }
    }

    public static function create(string $id, string $locationId, string $name, int $capacity): self
    {
        return new self($id, $locationId, $name, $capacity);
    }

    public static function reconstitute(string $id, string $locationId, string $name, int $capacity): self
    {
        return new self($id, $locationId, $name, $capacity);
    }
}

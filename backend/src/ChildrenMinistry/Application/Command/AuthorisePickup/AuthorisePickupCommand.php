<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\AuthorisePickup;

use App\Shared\Application\CQRS\Command;

final readonly class AuthorisePickupCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}

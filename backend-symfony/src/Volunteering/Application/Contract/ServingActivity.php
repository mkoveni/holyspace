<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use InvalidArgumentException;

/** Identifies one activity (for example kind CHILDREN_SESSION) whose staffing may be locked. */
final readonly class ServingActivity
{
    public function __construct(public string $kind, public string $id)
    {
        if ('' === $kind || '' === $id) {
            throw new InvalidArgumentException('A serving activity needs a kind and an id.');
        }
    }
}

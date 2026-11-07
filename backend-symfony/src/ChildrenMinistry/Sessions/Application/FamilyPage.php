<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

/**
 * @template T
 */
final readonly class FamilyPage
{
    /** @param list<T> $items */
    public function __construct(public bool $verified, public array $items, public bool $hasMore)
    {
    }
}

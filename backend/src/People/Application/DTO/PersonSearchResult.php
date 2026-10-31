<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class PersonSearchResult
{
    /** @param list<PersonSearchItem> $items */
    public function __construct(public array $items, public bool $hasMore)
    {
    }
}

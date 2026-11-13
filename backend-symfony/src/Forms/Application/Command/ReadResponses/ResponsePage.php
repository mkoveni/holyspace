<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\ReadResponses;

use App\Forms\Application\Contract\ResponseView;

final readonly class ResponsePage
{
    /** @param list<ResponseView> $items */
    public function __construct(public array $items, public int $total, public bool $identified)
    {
    }
}

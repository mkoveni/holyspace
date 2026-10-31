<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetReceiptDetails;

use App\Shared\Application\CQRS\Query;

final readonly class GetReceiptDetailsQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}

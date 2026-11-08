<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\ListPayments;

final readonly class ListPaymentsQuery
{
    public function __construct(public int $limit, public int $offset, public bool $reviewOnly)
    {
    }
}

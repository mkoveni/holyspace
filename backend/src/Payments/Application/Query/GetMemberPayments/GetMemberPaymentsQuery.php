<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetMemberPayments;

use App\Shared\Application\CQRS\Query;

final readonly class GetMemberPaymentsQuery implements Query
{
    public function __construct(public string $personId)
    {
    }
}

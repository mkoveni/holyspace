<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetOwnedPayment;

use App\Shared\Application\CQRS\Query;

final readonly class GetOwnedPaymentQuery implements Query
{
    public function __construct(public string $personId, public string $id)
    {
    }
}

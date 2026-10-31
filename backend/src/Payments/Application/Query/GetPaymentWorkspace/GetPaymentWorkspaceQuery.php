<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetPaymentWorkspace;

use App\Shared\Application\CQRS\Query;

final readonly class GetPaymentWorkspaceQuery implements Query
{
    public function __construct(public bool $givingAccess, public bool $peopleAccess)
    {
    }
}

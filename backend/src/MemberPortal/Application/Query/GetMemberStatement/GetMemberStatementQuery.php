<?php

declare(strict_types=1);

namespace App\MemberPortal\Application\Query\GetMemberStatement;

use App\Shared\Application\CQRS\Query;

final readonly class GetMemberStatementQuery implements Query
{
    public function __construct(public string $from, public string $to)
    {
    }
}

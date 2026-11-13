<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\ListMemberForms;

final readonly class ListMemberFormsQuery
{
    public function __construct(public string $accountId)
    {
    }
}

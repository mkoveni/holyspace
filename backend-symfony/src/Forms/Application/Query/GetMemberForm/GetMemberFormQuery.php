<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetMemberForm;

final readonly class GetMemberFormQuery
{
    public function __construct(public string $accountId, public string $formId)
    {
    }
}

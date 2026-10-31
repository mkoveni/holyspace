<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetPublicForm;

use App\Shared\Application\CQRS\Query;

final readonly class GetPublicFormQuery implements Query
{
    public function __construct(public string $token)
    {
    }
}

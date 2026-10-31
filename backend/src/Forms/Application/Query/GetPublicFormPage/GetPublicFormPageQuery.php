<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetPublicFormPage;

use App\Shared\Application\CQRS\Query;

final readonly class GetPublicFormPageQuery implements Query
{
    public function __construct(public string $token)
    {
    }
}

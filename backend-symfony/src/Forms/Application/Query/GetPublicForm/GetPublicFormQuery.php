<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetPublicForm;

use SensitiveParameter;

final readonly class GetPublicFormQuery
{
    public function __construct(#[SensitiveParameter] public string $token)
    {
    }
}

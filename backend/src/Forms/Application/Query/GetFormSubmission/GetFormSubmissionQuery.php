<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetFormSubmission;

use App\Shared\Application\CQRS\Query;

final readonly class GetFormSubmissionQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}

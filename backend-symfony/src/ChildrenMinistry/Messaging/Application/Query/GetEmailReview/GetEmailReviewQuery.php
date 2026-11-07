<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Query\GetEmailReview;

final readonly class GetEmailReviewQuery
{
    public function __construct(public string $communicationId)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetVisitInvitation;

final readonly class GetVisitInvitationQuery
{
    public function __construct(public string $token)
    {
    }
}

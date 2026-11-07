<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Query\GetGuardianCommunication;

final readonly class GetGuardianCommunicationQuery
{
    public function __construct(public string $communicationId)
    {
    }
}

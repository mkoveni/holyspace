<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetAcknowledgement;

final readonly class GetAcknowledgementQuery
{
    public function __construct(public string $acknowledgementId)
    {
    }
}

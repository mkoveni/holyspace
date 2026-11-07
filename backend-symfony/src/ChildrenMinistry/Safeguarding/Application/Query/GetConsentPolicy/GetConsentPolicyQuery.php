<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Query\GetConsentPolicy;

final readonly class GetConsentPolicyQuery
{
    public function __construct(public string $policyId)
    {
    }
}

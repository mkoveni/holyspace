<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Query\GetConsentPolicy;

use App\ChildrenMinistry\Safeguarding\Application\ConsentPolicyView;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetConsentPolicyHandler
{
    public function __construct(private PermissionChecker $authorization, private SafeguardingReader $safeguarding)
    {
    }

    public function handle(GetConsentPolicyQuery $query): ConsentPolicyView
    {
        $this->authorization->requirePermission('children.read');

        return $this->safeguarding->consentPolicy($query->policyId) ?? throw ChildrenException::missing('Consent policy');
    }
}

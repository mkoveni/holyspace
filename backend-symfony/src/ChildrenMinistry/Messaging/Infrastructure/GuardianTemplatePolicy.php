<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Infrastructure;

use App\Communications\Application\Contract\TemplatePublicationPolicy;

/** Children templates keep the generated message and use only the variables the ministry can supply. */
final readonly class GuardianTemplatePolicy implements TemplatePublicationPolicy
{
    private const array VARIABLES = ['message', 'guardianName', 'childName', 'programmeName', 'sessionDate', 'policyName'];

    public function violation(string $key, string $subject, string $html, array $variables): ?string
    {
        if (!str_starts_with($key, 'children.')) {
            return null;
        }
        $known = [] === array_diff($variables, self::VARIABLES);
        $retainsMessage = 1 === preg_match('/\{\{\s*message\s*\}\}/', $html);

        return $known && $retainsMessage ? null : 'Children ministry templates must retain {{message}} in the body and use only message, guardianName, childName, programmeName, sessionDate or policyName.';
    }
}

<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure;

use App\Communications\Application\Contract\TemplatePublicationPolicy;
use App\Communities\Application\NoticeReview;

/** Java `CommunityTemplatePolicy`: the member notice keeps exactly its three variables, the message in the body. */
final readonly class CommunityTemplatePolicy implements TemplatePublicationPolicy
{
    private const array REQUIRED = ['name', 'community', 'message'];

    public function violation(string $key, string $subject, string $html, array $variables): ?string
    {
        if (NoticeReview::TEMPLATE !== $key) {
            return null;
        }
        if ([] !== array_diff(self::REQUIRED, $variables) || [] !== array_diff($variables, self::REQUIRED)
            || !str_contains($html, '{{message}}') || !str_contains($subject.$html, '{{community}}')) {
            return 'Member notices require name, community and message variables, with message in the body and community in the rendered notice.';
        }

        return null;
    }
}

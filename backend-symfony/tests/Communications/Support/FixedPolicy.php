<?php

declare(strict_types=1);

namespace App\Tests\Communications\Support;

use App\Communications\Application\Contract\TemplatePublicationPolicy;

/** A publication policy with a fixed verdict that remembers the last template it judged. */
final class FixedPolicy implements TemplatePublicationPolicy
{
    /** @var array{string, string, string, list<string>}|null */
    public ?array $seen = null;

    public function __construct(private readonly ?string $problem = null)
    {
    }

    public function violation(string $key, string $subject, string $html, array $variables): ?string
    {
        $this->seen = [$key, $subject, $html, $variables];

        return $this->problem;
    }
}

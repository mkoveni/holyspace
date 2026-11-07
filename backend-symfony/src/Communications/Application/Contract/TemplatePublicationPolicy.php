<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** Originating contexts may require particular variables or mandatory wording in the templates they use. */
interface TemplatePublicationPolicy
{
    /**
     * @param list<string> $variables
     *
     * @return string|null why the template may not be published, or null when it is acceptable
     */
    public function violation(string $key, string $subject, string $html, array $variables): ?string;
}

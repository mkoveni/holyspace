<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\PreviewTemplate;

final readonly class PreviewTemplateQuery
{
    /**
     * @param list<string>          $variables
     * @param array<string, string> $values
     */
    public function __construct(public string $subject, public string $html, public array $variables, public array $values)
    {
    }
}

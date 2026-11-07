<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

final readonly class TemplatePage
{
    /** @param list<TemplateView> $templates */
    public function __construct(public array $templates, public int $total)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

final readonly class PreviewPayload
{
    public function __construct(public array $filters)
    {
    }
}

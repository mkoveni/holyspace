<?php

declare(strict_types=1);

namespace App\Communications\Presentation\Http\DTO;

final readonly class TemplatePayload
{
    public function __construct(public string $name, public string $channel, public string $subject, public string $body)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Communications\Presentation\Http\DTO;

final readonly class PreferencePayload
{
    public function __construct(public string $personId, public string $channel, public bool $consent, public string $consentNote = '')
    {
    }
}

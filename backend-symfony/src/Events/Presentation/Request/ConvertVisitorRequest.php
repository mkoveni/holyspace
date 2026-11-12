<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

final readonly class ConvertVisitorRequest
{
    public function __construct(public bool $inviteToApp = false)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class MemberOffer
{
    public function __construct(public bool $enabled, public int $version)
    {
    }
}

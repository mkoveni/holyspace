<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

interface RichText
{
    /** @throws \App\Communications\Domain\CommunicationException for oversized, empty or unsafe content */
    public function sanitize(string $html): string;
}

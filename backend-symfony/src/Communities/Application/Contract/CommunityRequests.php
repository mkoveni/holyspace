<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

interface CommunityRequests
{
    public function target(string $requestId, string $hash): ?string;

    public function record(string $requestId, string $hash, string $targetId): void;
}

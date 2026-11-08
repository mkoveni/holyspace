<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/** Idempotency keys for community commands (`community_requests`). */
interface CommunityRequests
{
    public function find(string $requestId): ?StoredRequest;

    public function record(string $requestId, string $hash, string $targetId): void;
}

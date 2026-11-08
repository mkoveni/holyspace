<?php

declare(strict_types=1);

namespace App\Communities\Application;

use App\Communities\Application\Contract\CommunityRequests;
use App\Communities\Domain\CommunityException;

/** Replays a command sent again with the same request id; the same id with different details is a conflict. */
final readonly class RequestReplay
{
    public const string DIFFERENT_DETAILS = 'This request identifier was used for different details.';
    public const string ALREADY_USED = 'This request identifier was already used.';

    public function __construct(private CommunityRequests $requests)
    {
    }

    /** @return string|null the earlier result, or null when this request id is new */
    public function previous(?string $requestId, string $hash, string $mismatch = self::DIFFERENT_DETAILS): ?string
    {
        if (null === $requestId) {
            throw CommunityException::invalid('A request identifier is required.');
        }
        $stored = $this->requests->find($requestId);
        if (null === $stored) {
            return null;
        }
        if (!hash_equals($stored->hash, $hash)) {
            throw CommunityException::conflict($mismatch);
        }

        return $stored->targetId;
    }

    public function record(string $requestId, string $hash, string $targetId): void
    {
        $this->requests->record($requestId, $hash, $targetId);
    }
}

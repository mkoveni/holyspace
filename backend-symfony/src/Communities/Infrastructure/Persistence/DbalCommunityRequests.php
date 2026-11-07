<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Application\Contract\CommunityRequests;
use Doctrine\DBAL\Connection;

final readonly class DbalCommunityRequests implements CommunityRequests
{
    public function __construct(private Connection $connection)
    {
    }

    public function target(string $requestId, string $hash): ?string
    {
        $row = $this->connection->fetchAssociative(
            'SELECT request_hash, target_id FROM community_requests WHERE id = :id FOR UPDATE',
            ['id' => $requestId]
        );
        if ($row === false) {
            return null;
        }
        if (!hash_equals($row['request_hash'], $hash)) {
            throw \App\Communities\Domain\CommunityException::conflict('This request identifier was used for different details.');
        }

        return $row['target_id'];
    }

    public function record(string $requestId, string $hash, string $targetId): void
    {
        $this->connection->insert('community_requests', ['id' => $requestId, 'request_hash' => $hash, 'target_id' => $targetId]);
    }
}

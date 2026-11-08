<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Application\Contract\CommunityRequests;
use App\Communities\Application\Contract\StoredRequest;
use App\Communities\Domain\CommunityException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DbalCommunityRequests implements CommunityRequests
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(string $requestId): ?StoredRequest
    {
        $row = $this->connection->createQueryBuilder()
            ->select('request_hash', 'target_id')
            ->from('community_requests')
            ->where('id = :id')
            ->setParameter('id', $requestId)
            ->fetchAssociative();

        // request_hash is CHAR(64) in V18; Java trims it too.
        return false === $row ? null : new StoredRequest(trim((string) $row['request_hash']), (string) $row['target_id']);
    }

    public function record(string $requestId, string $hash, string $targetId): void
    {
        try {
            $this->connection->insert('community_requests', ['id' => $requestId, 'request_hash' => $hash, 'target_id' => $targetId]);
        } catch (UniqueConstraintViolationException) {
            throw CommunityException::clash();
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Application\Contract\NoticeReader;
use App\Communities\Application\Contract\NoticeView;
use App\Communities\Domain\CommunityNotice;
use App\Communities\Domain\Repository\NoticeRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;

/** `community_notices` (V18). Hashes are CHAR(64), so they are trimmed when read, as in Java. */
final readonly class DbalNoticeStore implements NoticeReader, NoticeRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function notice(string $id): ?NoticeView
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'community_id', 'membership_id', 'contact_id', 'request_hash', 'review_hash', 'created_by', 'delivery_id')
            ->from('community_notices')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : new NoticeView(
            (string) $row['id'],
            (string) $row['community_id'],
            (string) $row['membership_id'],
            (string) $row['contact_id'],
            trim((string) $row['request_hash']),
            trim((string) $row['review_hash']),
            (string) $row['created_by'],
            (string) $row['delivery_id'],
        );
    }

    public function noticeIds(string $communityId, int $limit, int $offset): array
    {
        return array_values(array_map(strval(...), $this->connection->createQueryBuilder()
            ->select('id')
            ->from('community_notices')
            ->where('community_id = :community')
            ->setParameter('community', $communityId)
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchFirstColumn()));
    }

    public function insert(CommunityNotice $notice): void
    {
        $this->connection->insert('community_notices', [
            'id' => $notice->id,
            'community_id' => $notice->communityId,
            'membership_id' => $notice->membershipId,
            'contact_id' => $notice->contactId,
            'request_hash' => $notice->requestHash,
            'review_hash' => $notice->reviewHash,
            'evidence' => $notice->evidence,
            'created_by' => $notice->createdBy,
            'created_at' => Timestamps::toDatabase($notice->createdAt),
            'delivery_id' => $notice->deliveryId,
        ]);
    }
}

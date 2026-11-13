<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Application\Contract\CommunityMembershipReader;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalCommunityMembershipReader implements CommunityMembershipReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function activeCommunityIds(string $personId, DateTimeImmutable $at): array
    {
        $at = $at->format('Y-m-d H:i:sP');

        return array_values(array_map('strval', $this->connection->createQueryBuilder()
            ->select('c.id')
            ->from('community_memberships', 'm')
            ->join('m', 'communities', 'c', 'c.id = m.community_id')
            ->where('m.person_id = :person')
            ->andWhere('c.active')
            ->andWhere('m.started_at <= :at')
            ->andWhere('m.ended_at IS NULL OR m.ended_at > :at')
            ->orderBy('c.id')
            ->setParameter('person', $personId)
            ->setParameter('at', $at)
            ->fetchFirstColumn()));
    }
}

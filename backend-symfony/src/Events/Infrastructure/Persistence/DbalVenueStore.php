<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\VenuePage;
use App\Events\Application\Contract\VenueReader;
use App\Events\Application\Contract\VenueView;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\VenueRepository;
use App\Events\Domain\Venue;
use App\Shared\Infrastructure\Persistence\LikePattern;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class DbalVenueStore implements VenueRepository, VenueReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findForUpdate(string $id): ?Venue
    {
        return $this->aggregate($this->query($id)->forUpdate()->fetchAssociative());
    }

    public function findUnlocked(string $id): ?Venue
    {
        return $this->aggregate($this->query($id)->fetchAssociative());
    }

    /** @param array<string, mixed>|false $row */
    private function aggregate(array|false $row): ?Venue
    {
        return false === $row ? null : Venue::reconstitute(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['address'],
            (bool) $row['exclusive'],
            (bool) $row['active'],
            (int) $row['version'],
        );
    }

    private function query(string $id): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->select('*')->from('event_venues')->where('id = :id')->setParameter('id', $id);
    }

    public function find(string $id): ?VenueView
    {
        $row = $this->query($id)->fetchAssociative();

        return false === $row ? null : self::view($row);
    }

    public function insert(Venue $venue): void
    {
        $this->connection->insert('event_venues', [
            'id' => $venue->id,
            'name' => $venue->name,
            'address' => $venue->address,
            'exclusive' => $venue->exclusive,
            'active' => $venue->active,
            'version' => $venue->version,
        ], ['exclusive' => ParameterType::BOOLEAN, 'active' => ParameterType::BOOLEAN]);
    }

    public function save(Venue $venue): void
    {
        $changed = $this->connection->update('event_venues', [
            'name' => $venue->name,
            'address' => $venue->address,
            'active' => $venue->active,
            'version' => $venue->version,
        ], ['id' => $venue->id, 'version' => $venue->version - 1], ['active' => ParameterType::BOOLEAN]);
        if (1 !== $changed) {
            throw EventException::conflict('Venue changed. Refresh first.');
        }
    }

    public function search(string $search, bool $includeArchived, int $limit, int $offset): VenuePage
    {
        $rows = $this->matching($search, $includeArchived)
            ->select('*')
            ->orderBy('name')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();
        $total = $this->matching($search, $includeArchived)->select('COUNT(*)')->fetchOne();

        return new VenuePage(array_values(array_map(
            self::view(...),
            $rows,
        )), (int) $total);
    }

    /** @param array<string, mixed> $row */
    private static function view(array $row): VenueView
    {
        return new VenueView(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['address'],
            (bool) $row['exclusive'],
            (bool) $row['active'],
            (int) $row['version'],
        );
    }

    private function matching(string $search, bool $includeArchived): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->from('event_venues')
            ->where('name ILIKE :pattern')
            ->setParameter('pattern', LikePattern::contains($search));
        if (!$includeArchived) {
            $query->andWhere('active');
        }

        return $query;
    }
}

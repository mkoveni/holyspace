<?php

declare(strict_types=1);

namespace App\People\Infrastructure\ReadModel\DBAL;

use App\People\Application\DTO\PersonSearchItem;
use App\People\Application\DTO\PersonSearchResult;
use App\People\Application\ReadModel\PersonSearchReader;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PersonSearchReader::class)]
final readonly class DbalPersonSearchReader implements PersonSearchReader
{
    public function __construct(private Connection $db)
    {
    }

    public function search(string $query, int $limit = 20, array $excludeIds = [], array $ids = []): PersonSearchResult
    {
        if ($limit < 1 || $limit > 50 || count($ids) > 50 || count($excludeIds) > 50) {
            throw new InvalidArgumentException('Search requests are limited to 50 people.');
        }
        $query = trim($query);
        if ([] === $ids && mb_strlen($query) < 2) {
            return new PersonSearchResult([], false);
        }
        $builder = $this->db->createQueryBuilder()
            ->select('p.id', 'p.first_name', 'p.last_name', 'p.date_of_birth', 'p.email', 'p.phone')
            ->from('people_persons', 'p')
            ->orderBy('p.last_name', 'ASC')->addOrderBy('p.first_name', 'ASC')->addOrderBy('p.id', 'ASC')
            ->setMaxResults($limit + 1);
        if ([] !== $ids) {
            $builder->andWhere('p.id IN (:ids)')->setParameter('ids', $ids, ArrayParameterType::STRING);
        } else {
            foreach (preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) as $index => $term) {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
                $conditions = [];
                foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
                    $conditions[] = "LOWER(p.$field) LIKE :term$index ESCAPE '!'";
                }
                $digits = preg_replace('/[^0-9+]/', '', $term);
                if ('' !== $digits && preg_match('/^[0-9+()\-]+$/', $term)) {
                    $conditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(p.phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE :phone$index";
                    $builder->setParameter('phone'.$index, '%'.$digits.'%');
                }
                $builder->andWhere('('.implode(' OR ', $conditions).')')->setParameter('term'.$index, $pattern);
            }
        }
        if ([] !== $excludeIds) {
            $builder->andWhere('p.id NOT IN (:excluded)')->setParameter('excluded', $excludeIds, ArrayParameterType::STRING);
        }
        $rows = $builder->fetchAllAssociative();
        $hasMore = count($rows) > $limit;

        return new PersonSearchResult(array_map(static fn (array $row) => new PersonSearchItem(
            $row['id'], $row['first_name'], $row['last_name'], $row['date_of_birth'], $row['email'], $row['phone'],
        ), array_slice($rows, 0, $limit)), $hasMore);
    }
}

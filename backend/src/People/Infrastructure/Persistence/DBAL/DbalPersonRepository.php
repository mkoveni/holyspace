<?php

declare(strict_types=1);

namespace App\People\Infrastructure\Persistence\DBAL;

use App\People\Domain\Model\Person;
use App\People\Domain\Repository\PersonRepository;
use App\People\Domain\ValueObject\PersonId;
use Doctrine\DBAL\Connection;

final readonly class DbalPersonRepository implements PersonRepository
{
    public function __construct(private Connection $connection, private PersonMapper $mapper) {}
    public function save(Person $p): void
    {
        $d = $this->mapper->toRow($p);
        $exists = $this->connection->createQueryBuilder()->select('1')->from('people_persons')->where('id = :id')->setParameter('id', $p->id()->toString())->setMaxResults(1)->fetchOne();
        if ($exists !== false) {
            unset($d['id']);
            $this->connection->update('people_persons', $d, ['id' => $p->id()->toString()]);
        } else {
            $this->connection->insert('people_persons', $d);
        }
    }
    public function findById(PersonId $id): ?Person
    {
        $r = $this->connection->createQueryBuilder()->select('p.*')->from('people_persons', 'p')->where('p.id = :id')->setParameter('id', $id->toString())->fetchAssociative();
        return $r === false ? null : $this->mapper->toDomain($r);
    }
    public function exists(PersonId $id): bool
    {
        return $this->connection->createQueryBuilder()->select('1')->from('people_persons')->where('id = :id')->setParameter('id', $id->toString())->setMaxResults(1)->fetchOne() !== false;
    }
    public function findAll(): array
    {
        $rows = $this->connection->createQueryBuilder()->select('p.*')->from('people_persons', 'p')->orderBy('p.last_name', 'ASC')->addOrderBy('p.first_name', 'ASC')->fetchAllAssociative();
        return array_map(fn(array $r): Person => $this->mapper->toDomain($r), $rows);
    }
}

<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\ReadModel\DBAL;

use App\IdentityAccess\Application\ReadModel\AccessCatalogue;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AccessCatalogue::class)]
final readonly class DbalAccessCatalogue implements AccessCatalogue
{
    public function __construct(private Connection $db)
    {
    }

    public function permissions(): array
    {
        return $this->db->fetchAllAssociative('SELECT id,code,description FROM permissions ORDER BY code');
    }

    public function roles(): array
    {
        return $this->db->fetchAllAssociative('SELECT id,name,description FROM roles ORDER BY name');
    }

    public function accounts(string $search = '', int $limit = 50): array
    {
        if ($limit < 1 || $limit > 100 || mb_strlen($search) > 100) {
            throw new InvalidArgumentException('Invalid account search.');
        }
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($search)).'%';
        $rows = $this->db->createQueryBuilder()->select('id,username,person_id,status,created_at,updated_at')->from('user_accounts')->where("username LIKE :search ESCAPE '!'")->setParameter('search', $pattern)->orderBy('username')->setMaxResults($limit)->fetchAllAssociative();

        return array_map(fn ($r) => ['id' => $r['id'], 'username' => $r['username'], 'personId' => $r['person_id'], 'status' => $r['status'], 'createdAt' => (new DateTimeImmutable($r['created_at']))->format(DATE_ATOM), 'updatedAt' => (new DateTimeImmutable($r['updated_at']))->format(DATE_ATOM)], $rows);
    }
}

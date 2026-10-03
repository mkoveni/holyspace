<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\ReadModel\DBAL;

use App\IdentityAccess\Application\ReadModel\IdentityAccessReadRepository;
use Doctrine\DBAL\Connection;

final readonly class DbalIdentityAccessReadRepository implements IdentityAccessReadRepository
{
    public function __construct(private Connection $db) {}
    public function permissionCodesForUser(string $userId): array
    {
        return $this->db->createQueryBuilder()->select('DISTINCT p.code')->from('user_roles', 'ur')->innerJoin('ur', 'role_permissions', 'rp', 'rp.role_id = ur.role_id')->innerJoin('rp', 'permissions', 'p', 'p.id = rp.permission_id')->where('ur.user_id = :userId')->setParameter('userId', $userId)->orderBy('p.code', 'ASC')->fetchFirstColumn();
    }
}

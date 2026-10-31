<?php

declare(strict_types=1);

namespace App\Reporting\Infrastructure\ReadModel;

use App\IdentityAccess\Application\ReadModel\IdentityAccessReadRepository;
use App\Reporting\Application\FollowupAccounts;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(FollowupAccounts::class)]
final readonly class DbalFollowupAccounts implements FollowupAccounts
{
    public function __construct(private Connection $db, private IdentityAccessReadRepository $permissions)
    {
    }

    public function eligible(string $id, array $required): bool
    {
        if ('active' !== $this->db->fetchOne('SELECT status FROM user_accounts WHERE id=?', [$id])) {
            return false;
        }
        $codes = $this->permissions->permissionCodesForUser($id);

        return $this->hasPermissions($codes, $required);
    }

    private function hasPermissions(array $codes, array $required): bool
    {
        foreach ($required as $code) {
            if (!in_array($code, $codes, true) && !('followups.work' === $code && in_array('followups.manage', $codes, true)) && !(str_ends_with($code, '.read') && in_array(substr($code, 0, -5).'.manage', $codes, true))) {
                return false;
            }
        }

        return true;
    }

    public function name(string $id): string
    {
        return $this->names([$id])[$id] ?? 'Unavailable account';
    }

    public function names(array $ids): array
    {
        $names = [];
        foreach (array_chunk(array_values(array_unique($ids)), 500) as $chunk) {
            foreach ($this->db->fetchAllAssociative('SELECT id, username FROM user_accounts WHERE id IN (?)', [$chunk], [ArrayParameterType::STRING]) as $row) {
                $names[$row['id']] = $row['username'];
            }
        }

        return $names;
    }

    public function assignees(array $required): array
    {
        $accounts = [];
        $codes = [];
        $rows = $this->db->fetchAllAssociative("SELECT u.id, u.username, p.code FROM user_accounts u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN role_permissions rp ON rp.role_id = ur.role_id LEFT JOIN permissions p ON p.id = rp.permission_id WHERE u.status = 'active' ORDER BY u.username");
        foreach ($rows as $row) {
            $accounts[$row['id']] = ['id' => $row['id'], 'username' => $row['username']];
            if (null !== $row['code']) {
                $codes[$row['id']][] = $row['code'];
            }
        }

        return array_values(array_filter($accounts, fn ($account) => $this->hasPermissions($codes[$account['id']] ?? [], ['followups.work', ...$required])));
    }
}

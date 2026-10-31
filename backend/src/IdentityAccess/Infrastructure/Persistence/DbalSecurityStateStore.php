<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence;

use App\IdentityAccess\Application\Port\SecurityStateReader;
use App\IdentityAccess\Application\Port\SecurityStateStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;

#[AsAlias(SecurityStateStore::class)]
#[AsAlias(SecurityStateReader::class)]
final readonly class DbalSecurityStateStore implements SecurityStateStore
{
    public function __construct(private Connection $db)
    {
    }

    public function find(string $userId): ?array
    {
        return $this->db->fetchAssociative('SELECT * FROM account_security WHERE user_id = ?', [$userId]) ?: null;
    }

    public function save(array $state): void
    {
        $id = $state['user_id'];
        unset($state['user_id']);
        if ($this->db->fetchOne('SELECT 1 FROM account_security WHERE user_id = ?', [$id])) {
            $this->db->update('account_security', $state, ['user_id' => $id]);
        } else {
            $this->db->insert('account_security', ['user_id' => $id] + $state);
        }
    }

    public function audit(string $userId, ?string $actorId, string $action, string $reference, DateTimeImmutable $at): void
    {
        $this->db->insert('account_security_audit', ['id' => Uuid::v7()->toRfc4122(), 'user_id' => $userId, 'actor_id' => $actorId, 'action' => $action, 'reference' => $reference, 'occurred_at' => $at->format('Y-m-d H:i:s')]);
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20261021030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the default administrator role and grant every existing permission.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');
        $this->addSql(<<<'SQL'
INSERT INTO roles (id, name, description, created_at, updated_at)
SELECT ?, 'administrator', 'Default administrator with all permissions', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE LOWER(name) = 'administrator')
ON CONFLICT (name) DO NOTHING
SQL, [Uuid::v7()->toRfc4122()]);
        $this->addSql(<<<'SQL'
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE LOWER(r.name) IN ('admin', 'administrator', 'church admin', 'super admin')
ON CONFLICT (role_id, permission_id) DO NOTHING
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Administrator assignments may predate this migration; do not revoke them implicitly.');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20261011010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Require explicit permission to manage accounts, roles and permissions.';
    }

    public function up(Schema $schema): void
    {
        $id = Uuid::v7()->toRfc4122();
        $this->addSql("INSERT INTO permissions (id, code, description, created_at, updated_at) VALUES (?, 'identity.manage', 'Manage accounts, roles and permissions', NOW(), NOW())", [$id]);
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id) SELECT id, ? FROM roles WHERE LOWER(name) IN ('admin', 'administrator', 'church admin', 'super admin')", [$id]);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE p.code = 'identity.manage'");
        $this->addSql("DELETE FROM permissions WHERE code = 'identity.manage'");
    }
}

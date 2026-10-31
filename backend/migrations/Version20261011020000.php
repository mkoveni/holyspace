<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20261011020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Explicit read and management access for people, families, events and life events.';
    }

    public function up(Schema $schema): void
    {
        foreach (['people', 'families', 'events', 'life_events'] as $context) {
            foreach (['read', 'manage'] as $action) {
                $id = Uuid::v7()->toRfc4122();
                $code = $context.'.'.$action;
                $this->addSql('INSERT INTO permissions (id, code, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())', [$id, $code, ucfirst($action).' '.$context]);
                $this->addSql("INSERT INTO role_permissions (role_id, permission_id) SELECT id, ? FROM roles WHERE LOWER(name) IN ('admin', 'administrator', 'church admin', 'super admin')", [$id]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $codes = "'people.read','people.manage','families.read','families.manage','events.read','events.manage','life_events.read','life_events.manage'";
        $this->addSql("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE p.code IN ($codes)");
        $this->addSql("DELETE FROM permissions WHERE code IN ($codes)");
    }
}

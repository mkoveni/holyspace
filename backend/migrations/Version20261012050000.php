<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20261012050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Saved segments, follow-up tasks and opt-in daily automation with explicit reporting permissions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reporting_records(id CHAR(36) PRIMARY KEY,kind VARCHAR(40) NOT NULL,payload JSON NOT NULL,version INT NOT NULL,INDEX idx_reporting_kind(kind)) ENGINE=InnoDB');
        foreach (['reports.read', 'reports.manage', 'followups.work', 'followups.manage'] as $code) {
            $id = Uuid::v7()->toRfc4122();
            $this->addSql('INSERT INTO permissions(id,code,description,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())', [$id, $code, $code]);
            $this->addSql("INSERT INTO role_permissions(role_id,permission_id) SELECT id,? FROM roles WHERE LOWER(name) IN ('admin','administrator','church admin','super admin')", [$id]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE p.code IN ('reports.read','reports.manage','followups.work','followups.manage')");
        $this->addSql("DELETE FROM permissions WHERE code IN ('reports.read','reports.manage','followups.work','followups.manage')");
        $this->addSql('DROP TABLE reporting_records');
    }
}

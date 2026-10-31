<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20261012060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ZAR hosted payment orders, verified charges and refunds, notification deduplication and explicit payment management permission.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE payments_records(id CHAR(36) PRIMARY KEY,kind VARCHAR(40) NOT NULL,payload JSON NOT NULL,version INT NOT NULL,external_key VARCHAR(100) NULL UNIQUE,INDEX idx_payments_kind(kind)) ENGINE=InnoDB');
        $this->addSql('CREATE TABLE payment_notifications(id CHAR(64) PRIMARY KEY,fingerprint CHAR(64) NOT NULL,received_at DATETIME NOT NULL) ENGINE=InnoDB');
        $id = Uuid::v7()->toRfc4122();
        $this->addSql('INSERT INTO permissions(id,code,description,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())', [$id, 'payments.manage', 'Manage hosted payments and reconciliation']);
        $this->addSql("INSERT INTO role_permissions(role_id,permission_id) SELECT id,? FROM roles WHERE LOWER(name) IN ('admin','administrator','church admin','super admin')", [$id]);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE p.code='payments.manage'");
        $this->addSql("DELETE FROM permissions WHERE code='payments.manage'");
        $this->addSql('DROP TABLE payment_notifications');
        $this->addSql('DROP TABLE payments_records');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20261010010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Volunteering aggregates, durable occurrence integration messages, and scoped workflow permissions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE volunteering_records (id CHAR(36) PRIMARY KEY, kind VARCHAR(40) NOT NULL, payload JSON NOT NULL, version INT NOT NULL, INDEX idx_volunteering_kind (kind)) ENGINE=InnoDB');
        $this->addSql('CREATE TABLE integration_outbox (sequence_no BIGINT AUTO_INCREMENT PRIMARY KEY, id CHAR(36) NOT NULL UNIQUE, type VARCHAR(100) NOT NULL, schema_version INT NOT NULL, payload JSON NOT NULL, created_at VARCHAR(35) NOT NULL, processed_at VARCHAR(35) DEFAULT NULL, INDEX idx_outbox_pending (processed_at, sequence_no)) ENGINE=InnoDB');
        $this->addSql('CREATE TABLE integration_deliveries (message_id CHAR(36) NOT NULL, consumer VARCHAR(100) NOT NULL, PRIMARY KEY(message_id, consumer)) ENGINE=InnoDB');
        foreach (['volunteering', 'children', 'giving', 'attendance', 'ministries', 'communications'] as $context) {
            $id = Uuid::v7()->toRfc4122();
            $code = $context.'.manage';
            $this->addSql('INSERT INTO permissions (id, code, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())', [$id, $code, 'Manage '.$context.' workflows']);
            $this->addSql("INSERT INTO role_permissions (role_id, permission_id) SELECT id, ? FROM roles WHERE LOWER(name) IN ('admin', 'administrator', 'church admin', 'super admin')", [$id]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE rp FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE p.code IN ('volunteering.manage', 'children.manage', 'giving.manage', 'attendance.manage', 'ministries.manage', 'communications.manage')");
        $this->addSql("DELETE FROM permissions WHERE code IN ('volunteering.manage', 'children.manage', 'giving.manage', 'attendance.manage', 'ministries.manage', 'communications.manage')");
        $this->addSql('DROP TABLE integration_deliveries');
        $this->addSql('DROP TABLE integration_outbox');
        $this->addSql('DROP TABLE volunteering_records');
    }
}

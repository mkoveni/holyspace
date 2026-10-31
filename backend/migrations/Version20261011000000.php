<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Append-only operational change journal with account attribution and resource versions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE workflow_audit (id CHAR(36) NOT NULL PRIMARY KEY, context VARCHAR(40) NOT NULL, record_id CHAR(36) NOT NULL, kind VARCHAR(40) NOT NULL, action VARCHAR(20) NOT NULL, version INT NOT NULL, actor_id CHAR(36) DEFAULT NULL, occurred_at VARCHAR(35) NOT NULL, UNIQUE INDEX uq_workflow_audit_version (context, record_id, version), INDEX idx_workflow_audit_record (context, record_id, occurred_at)) ENGINE=InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE workflow_audit');
    }
}

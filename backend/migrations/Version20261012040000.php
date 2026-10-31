<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expiring administrator recovery codes and metadata-only account security audit.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_security ADD recovery_until BIGINT NOT NULL DEFAULT 0');
        $this->addSql('CREATE TABLE account_security_audit(id CHAR(36) PRIMARY KEY, user_id CHAR(36) NOT NULL, actor_id CHAR(36) DEFAULT NULL, action VARCHAR(40) NOT NULL, reference VARCHAR(200) NOT NULL, occurred_at DATETIME NOT NULL, INDEX idx_security_audit_user(user_id, occurred_at)) ENGINE=InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE account_security_audit');
        $this->addSql('ALTER TABLE account_security DROP recovery_until');
    }
}

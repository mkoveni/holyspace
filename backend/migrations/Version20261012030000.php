<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20261012030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Encrypted authenticator secrets, replay prevention, recovery codes and explicit member portal permission.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE account_security(user_id CHAR(36) PRIMARY KEY, secret TEXT DEFAULT NULL, pending_secret TEXT DEFAULT NULL, pending_until BIGINT NOT NULL DEFAULT 0, last_step BIGINT NOT NULL DEFAULT -1, recovery_hashes JSON NOT NULL, version INT NOT NULL DEFAULT 0, CONSTRAINT fk_security_user FOREIGN KEY(user_id) REFERENCES user_accounts(id) ON DELETE CASCADE) ENGINE=InnoDB');
        $id = Uuid::v7()->toRfc4122();
        $this->addSql('INSERT INTO permissions(id,code,description,created_at,updated_at) VALUES (?,?,?,NOW(),NOW())', [$id, 'member.portal', 'Own-record member portal with mandatory authenticator verification']);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE p.code='member.portal'");
        $this->addSql("DELETE FROM permissions WHERE code='member.portal'");
        $this->addSql('DROP TABLE account_security');
    }
}

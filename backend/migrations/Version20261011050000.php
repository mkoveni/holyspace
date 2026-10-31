<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20261011050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reusable public forms, staff review, idempotent submissions and forms permissions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE forms_records(id CHAR(36) PRIMARY KEY, kind VARCHAR(40) NOT NULL, payload JSON NOT NULL, version INT NOT NULL, form_id CHAR(36) DEFAULT NULL, public_token CHAR(64) DEFAULT NULL, submission_key CHAR(36) DEFAULT NULL, UNIQUE INDEX uniq_form_token(public_token), UNIQUE INDEX uniq_form_submission(form_id, submission_key), INDEX idx_forms_kind(kind,id), INDEX idx_form_responses(kind,form_id,id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        foreach (['read', 'manage'] as $action) {
            $id = Uuid::v7()->toRfc4122();
            $this->addSql('INSERT INTO permissions(id,code,description,created_at,updated_at) VALUES (?,?,?,NOW(),NOW())', [$id, 'forms.'.$action, ucfirst($action).' church forms']);
            $this->addSql("INSERT INTO role_permissions(role_id,permission_id) SELECT id,? FROM roles WHERE LOWER(name) IN ('admin','administrator','church admin','super admin')", [$id]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE p.code IN ('forms.read','forms.manage')");
        $this->addSql("DELETE FROM permissions WHERE code IN ('forms.read','forms.manage')");
        $this->addSql('DROP TABLE forms_records');
    }
}

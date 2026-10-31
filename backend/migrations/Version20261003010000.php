<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store an explicitly selected primary contact for each family.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE people_families ADD primary_contact_person_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE people_families ADD CONSTRAINT fk_family_primary_contact FOREIGN KEY (primary_contact_person_id) REFERENCES people_persons (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE people_families DROP FOREIGN KEY fk_family_primary_contact');
        $this->addSql('ALTER TABLE people_families DROP primary_contact_person_id');
    }
}

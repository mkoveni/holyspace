<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Private transactional portal image storage with owner slots and replacement versions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE media_images (target VARCHAR(12) NOT NULL, owner_id CHAR(36) NOT NULL, version INT NOT NULL, mime VARCHAR(40) NULL, width INT NULL, height INT NULL, byte_size INT NULL, content MEDIUMBLOB NULL, updated_at VARCHAR(40) NOT NULL, PRIMARY KEY(target, owner_id)) ENGINE=InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE media_images');
    }
}

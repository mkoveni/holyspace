<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Membership context tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE persons (
                id CHAR(36) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                date_of_birth DATE DEFAULT NULL,
                email VARCHAR(255) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE families (
                id CHAR(36) NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE family_members (
                id CHAR(36) NOT NULL,
                family_id CHAR(36) NOT NULL,
                person_id CHAR(36) NOT NULL,
                relationship VARCHAR(30) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY(id),
                UNIQUE KEY uq_family_person (family_id, person_id),
                CONSTRAINT fk_family_member_family
                    FOREIGN KEY (family_id) REFERENCES families (id),
                CONSTRAINT fk_family_member_person
                    FOREIGN KEY (person_id) REFERENCES persons (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE marriages (
                id CHAR(36) NOT NULL,
                spouse_one_id CHAR(36) NOT NULL,
                spouse_two_id CHAR(36) NOT NULL,
                marriage_date DATE NOT NULL,
                status VARCHAR(30) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id),
                CONSTRAINT fk_marriage_spouse_one
                    FOREIGN KEY (spouse_one_id) REFERENCES persons (id),
                CONSTRAINT fk_marriage_spouse_two
                    FOREIGN KEY (spouse_two_id) REFERENCES persons (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE engagements (
                id CHAR(36) NOT NULL,
                person_one_id CHAR(36) NOT NULL,
                person_two_id CHAR(36) NOT NULL,
                engagement_date DATE NOT NULL,
                status VARCHAR(40) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id),
                CONSTRAINT fk_engagement_person_one
                    FOREIGN KEY (person_one_id) REFERENCES persons (id),
                CONSTRAINT fk_engagement_person_two
                    FOREIGN KEY (person_two_id) REFERENCES persons (id)
            )
        SQL);

        $this->addSql(
            'CREATE INDEX idx_marriages_anniversary ON marriages (marriage_date, status)'
        );

        $this->addSql(
            'CREATE INDEX idx_engagements_date ON engagements (engagement_date, status)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE engagements');
        $this->addSql('DROP TABLE marriages');
        $this->addSql('DROP TABLE family_members');
        $this->addSql('DROP TABLE families');
        $this->addSql('DROP TABLE persons');
    }
}

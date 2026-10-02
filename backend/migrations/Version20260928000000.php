<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260928000000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create LifeEvents bounded context tables with audit timestamps.'; }
    public function up(Schema $schema): void { $this->addSql(<<<'SQL'
CREATE TABLE life_events (
    id CHAR(36) NOT NULL,
    person_id CHAR(36) NOT NULL,
    type VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY(id)
)
SQL); $this->addSql('CREATE INDEX idx_life_events_person ON life_events (person_id)'); $this->addSql('CREATE INDEX idx_life_events_type_date ON life_events (type, event_date)'); $this->addSql('CREATE INDEX idx_life_events_person_type_date ON life_events (person_id, type, event_date)'); }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE life_events'); }
}

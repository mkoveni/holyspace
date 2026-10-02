<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260929000000 extends AbstractMigration
{
    public function getDescription():string{return 'Refactor LifeEvents to participant-based events with typed details and migrate marriages/engagements from Membership.';}
    public function up(Schema $schema):void{
        $this->addSql('RENAME TABLE life_events TO life_events_legacy');
        $this->addSql(<<<'SQL'
CREATE TABLE life_events (
 id CHAR(36) NOT NULL, type VARCHAR(50) NOT NULL, event_date DATE NOT NULL,
 notes TEXT DEFAULT NULL, status VARCHAR(30) NOT NULL, details_json JSON NOT NULL,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE TABLE life_event_participants (life_event_id CHAR(36) NOT NULL, person_id CHAR(36) NOT NULL, role VARCHAR(30) NOT NULL, PRIMARY KEY(life_event_id,person_id), CONSTRAINT fk_lep_event FOREIGN KEY(life_event_id) REFERENCES life_events(id) ON DELETE CASCADE)');
        $this->addSql('CREATE INDEX idx_lep_person ON life_event_participants(person_id)');
        $this->addSql('CREATE INDEX idx_lep_person_role ON life_event_participants(person_id,role)');
        $this->addSql('CREATE INDEX idx_life_events_type_date ON life_events(type,event_date)');
        $this->addSql('CREATE INDEX idx_life_events_status_date ON life_events(status,event_date)');
        $this->addSql("INSERT INTO life_events(id,type,event_date,notes,status,details_json,created_at,updated_at) SELECT id,type,event_date,notes,status,'{}',created_at,updated_at FROM life_events_legacy");
        $this->addSql("INSERT INTO life_event_participants(life_event_id,person_id,role) SELECT id,person_id,'subject' FROM life_events_legacy");
        $this->addSql('CREATE TEMPORARY TABLE life_event_migration_map (legacy_id CHAR(36) NOT NULL, life_event_id CHAR(36) NOT NULL, event_type VARCHAR(30) NOT NULL)');
        $this->addSql("INSERT INTO life_event_migration_map(legacy_id,life_event_id,event_type) SELECT id,UUID(),'marriage' FROM marriages");
        $this->addSql("INSERT INTO life_events(id,type,event_date,notes,status,details_json,created_at,updated_at) SELECT map.life_event_id,'marriage',m.marriage_date,NULL,CASE WHEN m.status='active' THEN 'active' ELSE 'completed' END,'{}',m.created_at,m.updated_at FROM marriages m INNER JOIN life_event_migration_map map ON map.legacy_id=m.id AND map.event_type='marriage'");
        $this->addSql("INSERT INTO life_event_participants(life_event_id,person_id,role) SELECT map.life_event_id,m.spouse_one_id,'spouse' FROM marriages m INNER JOIN life_event_migration_map map ON map.legacy_id=m.id AND map.event_type='marriage'");
        $this->addSql("INSERT INTO life_event_participants(life_event_id,person_id,role) SELECT map.life_event_id,m.spouse_two_id,'spouse' FROM marriages m INNER JOIN life_event_migration_map map ON map.legacy_id=m.id AND map.event_type='marriage'");
        $this->addSql("INSERT INTO life_event_migration_map(legacy_id,life_event_id,event_type) SELECT id,UUID(),'engagement' FROM engagements");
        $this->addSql("INSERT INTO life_events(id,type,event_date,notes,status,details_json,created_at,updated_at) SELECT map.life_event_id,'engagement',m.engagement_date,NULL,CASE WHEN m.status='active' THEN 'active' WHEN m.status='cancelled' THEN 'cancelled' ELSE 'completed' END,'{}',m.created_at,m.updated_at FROM engagements m INNER JOIN life_event_migration_map map ON map.legacy_id=m.id AND map.event_type='engagement'");
        $this->addSql("INSERT INTO life_event_participants(life_event_id,person_id,role) SELECT map.life_event_id,m.person_one_id,'proposer' FROM engagements m INNER JOIN life_event_migration_map map ON map.legacy_id=m.id AND map.event_type='engagement'");
        $this->addSql("INSERT INTO life_event_participants(life_event_id,person_id,role) SELECT map.life_event_id,m.person_two_id,'recipient' FROM engagements m INNER JOIN life_event_migration_map map ON map.legacy_id=m.id AND map.event_type='engagement'");
        $this->addSql('DROP TEMPORARY TABLE life_event_migration_map');
        $this->addSql('DROP TABLE life_events_legacy');
        $this->addSql('DROP TABLE engagements');
        $this->addSql('DROP TABLE marriages');
    }
    public function down(Schema $schema):void{throw new \RuntimeException('This migration is intentionally irreversible because it consolidates LifeEvents and Membership marriage/engagement data.');}
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Version document schemas independently, index delivery status, and prevent audit bypass by truncation.';
    }

    public function up(Schema $schema): void
    {
        foreach (['attendance_records', 'childrenministry_records', 'ministries_records', 'volunteering_records', 'serviceplanning_records', 'giving_records', 'payments_records', 'communications_records', 'reporting_records', 'forms_records', 'event_registrations', 'event_registration_settings'] as $table) {
            $this->addSql("ALTER TABLE $table ADD payload_schema_version integer NOT NULL DEFAULT 1 CHECK (payload_schema_version = 1)");
        }
        $this->addSql("CREATE INDEX idx_delivery_status ON communications_records ((payload->>'status')) WHERE kind = 'deliveries'");
        // TRUNCATE has no OLD rows; reject it rather than silently losing history.
        $this->addSql(<<<'SQL'
DO $$ DECLARE business_table record; BEGIN
    FOR business_table IN SELECT DISTINCT tgrelid::regclass::text AS name FROM pg_trigger WHERE tgname = 'audit_business_change' LOOP
        EXECUTE format('CREATE TRIGGER audit_no_truncate BEFORE TRUNCATE ON %s FOR EACH STATEMENT EXECUTE FUNCTION audit_immutable()', business_table.name);
    END LOOP;
END $$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Schema versions and audit coverage must be retained.');
    }
}

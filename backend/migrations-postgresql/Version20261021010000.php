<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Atomic field-level audit triggers for every business context, with redaction and immutable history.';
    }

    public function up(Schema $schema): void
    {
        // Explicit boundaries preserve semicolons inside PL/pgSQL function bodies.
        foreach (explode('-- @statement', file_get_contents(__DIR__.'/audit.sql')) as $sql) {
            if ('' !== trim($sql)) {
                $this->addSql($sql);
            }
        }
        foreach ([
            'identity' => ['user_accounts', 'roles', 'permissions', 'user_roles', 'role_permissions', 'account_security'],
            'people' => ['people_persons', 'people_relationships', 'people_person_addresses', 'people_communication_options', 'people_education_history', 'people_employment_history'],
            'families' => ['people_families', 'people_family_memberships', 'people_family_addresses'],
            'life_events' => ['life_events', 'life_event_participants'],
            'events' => ['church_events', 'church_event_occurrences', 'event_registration_settings', 'event_registrations'],
            'attendance' => ['attendance_records'], 'children' => ['childrenministry_records'],
            'ministries' => ['ministries_records'], 'volunteering' => ['volunteering_records'],
            'service_planning' => ['serviceplanning_records'], 'giving' => ['giving_records'],
            'payments' => ['payments_records'], 'communications' => ['communications_records'],
            'reporting' => ['reporting_records'], 'forms' => ['forms_records'], 'media' => ['media_images'],
        ] as $context => $tables) {
            foreach ($tables as $table) {
                $this->addSql("CREATE TRIGGER audit_business_change AFTER INSERT OR UPDATE OR DELETE ON $table FOR EACH ROW EXECUTE FUNCTION audit_capture('$context')");
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Audit history must be retained.');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Events\Infrastructure;

use App\Tests\Support\DatabaseTestCase;

/** The permission codes every Events handler will check must exist in the migrated schema. */
final class EventPermissionsTest extends DatabaseTestCase
{
    public function testEveryEventsPermissionIsSeededAndGrantedToAdministrators(): void
    {
        $codes = [
            'events.read',
            'events.manage',
            'events.registrations.read',
            'events.registrations.manage',
            'events.attendance.read',
            'events.attendance.manage',
            'events.communications.send',
            'events.visitors.read',
        ];

        $granted = $this->connection->fetchFirstColumn(
            "SELECT p.code FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN roles r ON r.id = rp.role_id WHERE r.name = 'administrator' AND p.code LIKE 'events.%'",
        );
        sort($codes);
        sort($granted);

        self::assertSame($codes, $granted);
    }

    public function testTheEventsTablesExist(): void
    {
        $tables = [
            'event_venues', 'event_definitions', 'event_series', 'event_occurrences', 'event_registration_policies',
            'event_registrations', 'event_registration_history', 'event_occurrence_history', 'event_requests', 'event_audit',
            'event_attendance', 'event_attendance_history', 'event_notifications', 'event_closeouts', 'event_closeout_history',
            'event_member_offers', 'event_admission_passes', 'event_cover_images', 'event_visit_links', 'event_visitor_check_ins',
            'event_visitor_children', 'event_visit_messaging', 'event_visitor_messages',
        ];

        $found = $this->connection->fetchFirstColumn("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE 'event\\_%'");
        sort($tables);
        sort($found);

        self::assertSame($tables, $found);
    }
}

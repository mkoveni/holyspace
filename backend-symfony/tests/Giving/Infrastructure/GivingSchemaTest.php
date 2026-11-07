<?php

declare(strict_types=1);

namespace App\Tests\Giving\Infrastructure;

use App\Tests\Support\DatabaseTestCase;

/** The permissions and tables every Giving session will rely on exist in the migrated schema. */
final class GivingSchemaTest extends DatabaseTestCase
{
    public function testEveryGivingPermissionIsSeededAndGrantedToAdministrators(): void
    {
        $codes = ['giving.read', 'giving.manage', 'giving.reverse', 'giving.acknowledge', 'giving.reconcile', 'giving.export'];

        $granted = $this->connection->fetchFirstColumn(
            "SELECT p.code FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN roles r ON r.id = rp.role_id WHERE r.name = 'administrator' AND p.code LIKE 'giving.%'",
        );
        sort($codes);
        sort($granted);

        self::assertSame($codes, $granted);
    }

    public function testTheGivingTablesExist(): void
    {
        $tables = [
            'giving_funds', 'giving_gifts', 'giving_allocations', 'giving_reversals', 'giving_history',
            'giving_pledges', 'giving_pledge_assignments', 'giving_acknowledgements', 'giving_acknowledgement_lines',
            'giving_reconciliation_batches', 'giving_reconciliation_items', 'giving_reconciliation_reviews',
            'giving_payment_sources', 'giving_payment_refunds',
            'giving_donor_links', 'giving_donor_link_history', 'giving_online_funds',
        ];

        $found = $this->connection->fetchFirstColumn("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE 'giving\\_%'");
        sort($tables);
        sort($found);

        self::assertSame($tables, $found);
    }

    public function testTheIntegrityTriggersThePortRelyOnExist(): void
    {
        $triggers = $this->connection->fetchFirstColumn(
            "SELECT tgname FROM pg_trigger WHERE NOT tgisinternal AND tgname LIKE 'giving\\_%'",
        );

        foreach (['giving_header_balance', 'giving_allocation_balance', 'giving_gift_immutable', 'giving_reversal_immutable', 'giving_online_source'] as $name) {
            self::assertContains($name, $triggers);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Operations;

use App\Shared\Application\ReadModel\ReadinessChecks;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Throwable;

#[AsAlias(ReadinessChecks::class)]
final readonly class Readiness implements ReadinessChecks
{
    public function __construct(private Connection $db)
    {
    }

    public function checks(): array
    {
        $checks = ['database' => false, 'schema' => false];
        try {
            $checks['database'] = 1 === (int) $this->db->fetchOne('SELECT 1');
            $count = (int) $this->db->fetchOne("SELECT COUNT(*) FROM permissions WHERE code IN ('people.read','people.manage','families.read','families.manage','events.read','events.manage','life_events.read','life_events.manage')");
            $this->db->fetchOne('SELECT COUNT(*) FROM integration_outbox');
            $this->db->fetchOne('SELECT COUNT(*) FROM workflow_audit');
            $this->db->fetchOne('SELECT COUNT(*) FROM account_security');
            $this->db->fetchOne('SELECT COUNT(*) FROM account_security_audit');
            $this->db->fetchOne('SELECT COUNT(*) FROM reporting_records');
            $this->db->fetchOne('SELECT COUNT(*) FROM payments_records');
            $this->db->fetchOne('SELECT COUNT(*) FROM payment_notifications');
            $this->db->fetchOne('SELECT COUNT(*) FROM media_images');
            $checks['schema'] = 8 === $count;
            if ($this->db->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $this->db->fetchOne('SELECT id FROM audit_entries LIMIT 1');
                $this->db->fetchOne('SELECT payload_schema_version FROM giving_records LIMIT 1');
                $missing = (int) $this->db->fetchOne("SELECT COUNT(*) FROM pg_tables t WHERE schemaname = 'public' AND tablename NOT IN ('audit_entries', 'workflow_audit', 'account_security_audit', 'integration_outbox', 'integration_deliveries', 'payment_notifications', 'doctrine_migration_versions', 'lock_keys') AND NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgrelid = ('public.' || t.tablename)::regclass AND tgname = 'audit_business_change')");
                $guard = (int) $this->db->fetchOne("SELECT COUNT(*) FROM pg_trigger WHERE tgrelid = 'audit_entries'::regclass AND tgname = 'audit_no_changes'");
                $checks['schema'] = $checks['schema'] && 0 === $missing && 1 === $guard;
            }
        } catch (Throwable) {
            $checks['schema'] = false;
            // Public probes expose no connection strings, SQL, or exception details.
        }

        return $checks;
    }
}

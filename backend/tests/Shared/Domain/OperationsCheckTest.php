<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Infrastructure\Operations\CheckOperationsCommand;
use App\Shared\Infrastructure\Operations\Readiness;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class OperationsCheckTest extends TestCase
{
    public function testBacklogsAndStuckAttemptsFailTheOperationalProbe(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach (['permissions(code TEXT)', 'integration_outbox(id TEXT, created_at TEXT, processed_at TEXT)', 'workflow_audit(id TEXT)', 'account_security(user_id TEXT)', 'account_security_audit(id TEXT)', 'reporting_records(id TEXT,kind TEXT,payload TEXT)', 'payments_records(id TEXT,kind TEXT,payload TEXT)', 'payment_notifications(id TEXT)', 'media_images(owner_id TEXT)', 'communications_records(kind TEXT, payload TEXT)'] as $table) {
            $db->executeStatement('CREATE TABLE '.$table);
        }
        foreach (['people', 'families', 'events', 'life_events'] as $context) {
            foreach (['read', 'manage'] as $action) {
                $db->insert('permissions', ['code' => $context.'.'.$action]);
            }
        }
        $tester = new CommandTester(new CheckOperationsCommand($db, new Readiness($db)));
        self::assertSame(0, $tester->execute([]));
        $db->insert('integration_outbox', ['id' => 'pending', 'created_at' => gmdate('Y-m-d H:i:s', time() - 600), 'processed_at' => null]);
        self::assertSame(1, $tester->execute([]));
        self::assertGreaterThanOrEqual(600, json_decode($tester->getDisplay(), true)['oldestOutboxSeconds']);
        $db->executeStatement('DELETE FROM integration_outbox');
        $db->insert('communications_records', ['kind' => 'deliveries', 'payload' => json_encode(['status' => 'sending', 'attemptedAt' => gmdate('c', time() - 600)])]);
        self::assertSame(1, $tester->execute([]));
        self::assertSame(1, json_decode($tester->getDisplay(), true)['stuckDeliveries']);
        $db->executeStatement('DELETE FROM communications_records');
        $db->insert('reporting_records', ['id' => 'rule', 'kind' => 'rules', 'payload' => json_encode(['reviewReason' => null])]);
        self::assertSame(0, $tester->execute([]));
        $db->update('reporting_records', ['payload' => json_encode(['reviewReason' => 'Assignee lost access.'])], ['id' => 'rule']);
        self::assertSame(1, $tester->execute([]));
        self::assertSame(1, json_decode($tester->getDisplay(), true)['followupRulesNeedingReview']);
    }
}

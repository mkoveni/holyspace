<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Operations;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'app:operations:check', description: 'Exit nonzero when the schema, outbox, or delivery attempts need operator attention.')]
final class CheckOperationsCommand extends Command
{
    public function __construct(private readonly Connection $db, private readonly Readiness $readiness)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $checks = $this->readiness->checks();
            $row = $this->db->fetchAssociative('SELECT COUNT(*) AS pending, MIN(created_at) AS oldest FROM integration_outbox WHERE processed_at IS NULL');
            $oldestAge = $row['oldest'] ? max(0, time() - (new DateTimeImmutable($row['oldest'], new DateTimeZone('UTC')))->getTimestamp()) : 0;
            $stuck = 0;
            // This is an operational probe, not an HTTP request or business mutation.
            $statusFilter = $this->db->getDatabasePlatform() instanceof PostgreSQLPlatform ? " AND payload->>'status' = 'sending'" : '';
            foreach ($this->db->fetchFirstColumn("SELECT payload FROM communications_records WHERE kind = 'deliveries'".$statusFilter) as $payload) {
                $delivery = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                if (($delivery['status'] ?? '') === 'sending' && (empty($delivery['attemptedAt']) || time() - strtotime($delivery['attemptedAt']) > 300)) {
                    ++$stuck;
                }
            }
            $pausedRules = 0;
            foreach ($this->db->fetchFirstColumn("SELECT payload FROM reporting_records WHERE kind='rules'") as $payload) {
                $rule = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                if (!empty($rule['reviewReason'])) {
                    ++$pausedRules;
                }
            }
            $paymentReview = 0;
            foreach ($this->db->fetchFirstColumn("SELECT payload FROM payments_records WHERE kind='orders'") as $payload) {
                $order = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                if (!empty($order['reviewReason']) || ($order['subscriptionStatus'] ?? '') === 'cancel_requested') {
                    ++$paymentReview;
                }
            }
            $healthy = !in_array(false, $checks, true) && (int) $row['pending'] < 500 && $oldestAge < 300 && 0 === $stuck && 0 === $pausedRules && 0 === $paymentReview;
            $output->writeln(json_encode(['healthy' => $healthy, 'checks' => $checks, 'pendingOutbox' => (int) $row['pending'], 'oldestOutboxSeconds' => $oldestAge, 'stuckDeliveries' => $stuck, 'followupRulesNeedingReview' => $pausedRules, 'paymentsNeedingReview' => $paymentReview], JSON_THROW_ON_ERROR));

            return $healthy ? Command::SUCCESS : Command::FAILURE;
        } catch (Throwable) {
            $output->writeln('{"healthy":false,"message":"Operational probe failed; check server logs and database connectivity."}');

            return Command::FAILURE;
        }
    }
}

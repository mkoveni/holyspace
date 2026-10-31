<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\WorkflowTransaction;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

#[AsAlias(IntegrationProcessor::class)]
final readonly class OutboxProcessor implements IntegrationProcessor
{
    public function __construct(private Connection $db, private WorkflowTransaction $transactions, #[AutowireIterator('onechurch.integration_consumer')] private iterable $consumers, private LoggerInterface $logger)
    {
    }

    public function drainAll(): void
    {
        while (100 === $this->drain()) {
        }
    }

    public function drain(int $limit = 100): int
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Outbox batch size must be between 1 and 1000.');
        }
        $count = 0;
        while ($count < $limit) {
            $messageId = null;
            try {
                $processed = $this->transactions->run(function () use (&$messageId): bool {
                    $row = $this->db->createQueryBuilder()->select('*')->from('integration_outbox')->where('processed_at IS NULL')->orderBy('sequence_no')->setMaxResults(1)->fetchAssociative();
                    if (false === $row) {
                        return false;
                    }
                    $messageId = $row['id'];
                    if (1 !== (int) $row['schema_version']) {
                        throw new DomainException('Unsupported integration message schema version.');
                    }
                    $payload = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
                    foreach ($this->consumers as $consumer) {
                        if (false !== $this->db->fetchOne('SELECT 1 FROM integration_deliveries WHERE message_id = ? AND consumer = ?', [$messageId, $consumer->name()])) {
                            continue;
                        }
                        $consumer->consume($row['type'], $payload);
                        $this->db->insert('integration_deliveries', ['message_id' => $messageId, 'consumer' => $consumer->name()]);
                    }
                    $this->db->update('integration_outbox', ['processed_at' => (new DateTimeImmutable())->format(DATE_ATOM)], ['id' => $messageId]);

                    return true;
                });
            } catch (Throwable $error) {
                if (null === $messageId) {
                    throw $error;
                }
                // Never log envelope data or raw exception messages: either may include private data.
                $this->logger->error('Integration message failed; processing stopped in sequence.', ['message_id' => $messageId, 'exception_class' => $error::class]);
                throw new DomainException('Integration processing failed for message '.$messageId.'. Earlier messages are committed; retry after resolving the failure.', 0, $error);
            }
            if (!$processed) {
                break;
            }
            ++$count;
        }

        return $count;
    }
}

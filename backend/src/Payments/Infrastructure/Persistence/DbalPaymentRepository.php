<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Payments\Domain\Model\PaymentCharge;
use App\Payments\Domain\Model\PaymentOrder;
use App\Payments\Domain\Model\PaymentRefund;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PaymentRepository::class)]
final class DbalPaymentRepository extends WorkflowDocumentRepository implements PaymentRepository
{
    public function __construct(Connection $db, private readonly PaymentMapper $paymentMapper, WorkflowAuditJournal $audit)
    {
        parent::__construct($db, $paymentMapper, 'payments_records', $audit);
    }

    public function save(WorkflowRecord $record): void
    {
        $this->db->transactional(function () use ($record) {
            parent::save($record);
            $key = match (true) {
                $record instanceof PaymentOrder => 'request:'.hash('sha256', $record->personId.':'.$record->requestKey),
                $record instanceof PaymentCharge => 'charge:'.hash('sha256', $record->providerReference),
                $record instanceof PaymentRefund => 'refund:'.hash('sha256', $record->providerReference),
                default => null,
            };
            $this->db->update('payments_records', ['external_key' => $key], ['id' => $record->id]);
        });
    }

    public function byExternalKey(string $key): ?WorkflowRecord
    {
        $row = $this->db->fetchAssociative('SELECT kind,payload FROM payments_records WHERE external_key=?', [$key]);

        return false === $row ? null : $this->paymentMapper->hydrate($row['kind'], $row['payload']);
    }

    public function notification(string $key): ?string
    {
        $value = $this->db->fetchOne('SELECT fingerprint FROM payment_notifications WHERE id=?', [hash('sha256', $key)]);

        return false === $value ? null : $value;
    }

    public function remember(string $key, string $fingerprint): void
    {
        $this->db->insert('payment_notifications', ['id' => hash('sha256', $key), 'fingerprint' => $fingerprint, 'received_at' => gmdate('Y-m-d H:i:s')]);
    }
}

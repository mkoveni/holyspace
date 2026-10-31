<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\ReadModel;

use App\Payments\Application\ReadModel\PaymentReadStore;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Domain\Model\WorkflowRecord;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PaymentReadStore::class)]
final readonly class RepositoryPaymentReadStore implements PaymentReadStore
{
    public function __construct(private PaymentRepository $records)
    {
    }

    public function find(string $kind, string $id): ?WorkflowRecord
    {
        return $this->records->find($kind, $id);
    }

    public function all(string $kind): array
    {
        return $this->records->all($kind);
    }

    public function byExternalKey(string $key): ?WorkflowRecord
    {
        return $this->records->byExternalKey($key);
    }

    public function notification(string $key): ?string
    {
        return $this->records->notification($key);
    }
}

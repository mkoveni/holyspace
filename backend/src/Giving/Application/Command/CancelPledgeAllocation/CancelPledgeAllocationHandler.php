<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CancelPledgeAllocation;

use App\Giving\Domain\Repository\GivingRepository;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CancelPledgeAllocationHandler
{
    public function __construct(private GivingRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(CancelPledgeAllocationCommand $message): object
    {
        $id = $message->id;
        $expectedVersion = $message->expectedVersion;
        $reason = $message->reason;

        return $this->transactions->run(function () use ($id, $expectedVersion, $reason) {
            $link = $this->required('pledge_allocations', $id);
            if ($link->version !== $expectedVersion) {
                throw new DomainException('This allocation changed. Reload before retrying.');
            }
            $this->records->save($link->cancel($reason));

            return $this->required('pledge_allocations', $id);
        });
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}

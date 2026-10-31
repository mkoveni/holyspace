<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\IssueReceipt;

use App\Giving\Domain\Model\DonationReceipt;
use App\Giving\Domain\Repository\GivingRepository;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class IssueReceiptHandler
{
    public function __construct(
        private GivingRepository $records,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(IssueReceiptCommand $message): object
    {
        $giftId = $message->giftId;

        return $this->transactions->run(function () use ($giftId) {
            $gift = $this->required('gifts', $giftId);
            if ('posted' !== $gift->status) {
                throw new DomainException('Cannot issue an acknowledgement for a reversed gift.');
            }
            foreach ($this->records->all('receipts') as $receipt) {
                if ($receipt->giftId === $giftId) {
                    return $receipt;
                }
            }

            return $this->save('receipts', new DonationReceipt($giftId, 'G-'.strtoupper($giftId), $gift->amountMinor, $gift->currency, $this->clock->now()->format('Y-m-d'), $gift->donorId));
        });
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }

    private function save(string $kind, object $record): object
    {
        $this->records->save($record);

        return $this->required($kind, $record->id);
    }
}

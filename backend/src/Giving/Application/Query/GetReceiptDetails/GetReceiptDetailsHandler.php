<?php

declare(strict_types=1);

namespace App\Giving\Application\Query\GetReceiptDetails;

use App\Giving\Application\ReadModel\GivingReadStore;
use App\People\Application\ReadModel\PersonDirectory;
use OutOfBoundsException;

final readonly class GetReceiptDetailsHandler
{
    public function __construct(private GivingReadStore $records, private PersonDirectory $people)
    {
    }

    public function __invoke(GetReceiptDetailsQuery $message): array
    {
        $id = $message->id;
        $receipt = $this->required('receipts', $id);
        $gift = $this->required('gifts', $receipt->giftId);

        return ['receipt' => $receipt, 'gift' => $gift, 'donor' => null === $gift->donorId ? null : $this->people->get($gift->donorId), 'notice' => 'Donation acknowledgement. This document makes no claim of tax deductibility.'];
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}

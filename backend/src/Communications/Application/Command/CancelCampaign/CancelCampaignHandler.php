<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\CancelCampaign;

use App\Communications\Domain\Repository\CommunicationsRepository;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CancelCampaignHandler
{
    public function __construct(private CommunicationsRepository $records, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(CancelCampaignCommand $message): object
    {
        $id = $message->id;

        return $this->transactions->run(function () use ($id) {
            $campaign = $this->required('campaigns', $id);
            foreach ($this->records->all('deliveries') as $delivery) {
                if ($delivery->campaignId === $id && 'pending' === $delivery->status) {
                    $this->records->save($delivery->changeStatus('suppressed', 'Campaign cancelled.'));
                }
            }

            return $this->save('campaigns', $campaign->changeStatus('cancelled'));
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

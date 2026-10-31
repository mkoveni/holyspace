<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\CreateCampaign;

use App\Communications\Domain\Model\Campaign;
use App\Communications\Domain\Repository\CommunicationsRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CreateCampaignHandler
{
    public function __construct(
        private CommunicationsRepository $records,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CreateCampaignCommand $message): object
    {
        $data = $message->data;

        return $this->transactions->run(function () use ($data) {
            $template = $this->required('templates', $data['templateId']);
            foreach ($data['recipientIds'] as $personId) {
                if (!$this->people->get($personId)) {
                    throw new InvalidArgumentException('Recipient not found.');
                }
            }

            return $this->save('campaigns', new Campaign($data['name'], $template->id, $data['recipientIds'], $template->channel, $template->subject, $template->body));
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

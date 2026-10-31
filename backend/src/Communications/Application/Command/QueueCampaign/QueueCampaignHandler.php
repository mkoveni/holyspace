<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\QueueCampaign;

use App\Communications\Domain\Model\MessageDelivery;
use App\Communications\Domain\Repository\CommunicationsRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class QueueCampaignHandler
{
    public function __construct(
        private CommunicationsRepository $records,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
        private iterable $guards = [],
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(QueueCampaignCommand $message): object
    {
        $id = $message->id;

        return $this->transactions->run(function () use ($id) {
            $campaign = $this->required('campaigns', $id);
            if ('queued' === $campaign->status) {
                return $campaign;
            }
            if ('draft' !== $campaign->status) {
                throw new DomainException('Campaign cannot be queued.');
            }
            foreach ($campaign->recipientIds as $personId) {
                $person = $this->people->get($personId);
                $body = str_replace('{{name}}', $person['name'] ?? '', $campaign->body);
                $subject = str_replace('{{name}}', $person['name'] ?? '', $campaign->subject);
                $reason = $this->suppressionReason($personId, $campaign->channel) ?? $this->guardReason($campaign->id);
                $delivery = new MessageDelivery($campaign->id, $personId, $campaign->channel, $subject, $body, null === $reason ? 'pending' : 'suppressed', $reason);
                $this->records->save($delivery);
            }

            return $this->save('campaigns', $campaign->changeStatus('queued'));
        });
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }

    private function suppressionReason(string $personId, string $channel): ?string
    {
        $consent = false;
        foreach ($this->records->all('preferences') as $preference) {
            if ($preference->personId === $personId && $preference->channel === $channel) {
                $consent = $preference->consent;
            }
        }
        if (!$consent) {
            return 'No current channel consent.';
        }
        $person = $this->people->get($personId);
        $contact = $person['email' === $channel ? 'email' : 'phone'] ?? null;
        if (!$contact) {
            return 'No contact address for this channel.';
        }
        if ('email' === $channel && !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email address.';
        }
        if ('sms' === $channel && !preg_match('/^\+[1-9]\d{7,14}$/', $contact)) {
            return 'SMS number must include an international country code.';
        }

        return null;
    }

    private function guardReason(string $campaignId): ?string
    {
        foreach ($this->guards as $guard) {
            if (($reason = $guard->suppressionReason($campaignId)) !== null) {
                return $reason;
            }
        }

        return null;
    }

    private function save(string $kind, object $record): object
    {
        $this->records->save($record);

        return $this->required($kind, $record->id);
    }
}

<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\DeliverNextMessage;

use App\Communications\Application\Port\MessageGateway;
use App\Communications\Domain\Repository\CommunicationsRepository;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

final readonly class DeliverNextMessageHandler
{
    public function __construct(
        private CommunicationsRepository $records,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
        private MessageGateway $gateway,
        private iterable $guards = [],
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(DeliverNextMessageCommand $message): bool
    {
        $delivery = $this->transactions->run(function () {
            foreach ($this->records->all('deliveries') as $candidate) {
                if ('pending' !== $candidate->status) {
                    continue;
                }
                if (!($this->gateway->configuration()[$candidate->channel] ?? false)) {
                    continue;
                }
                $campaign = $this->required('campaigns', $candidate->campaignId);
                $reason = 'cancelled' === $campaign->status ? 'Campaign cancelled.' : $this->suppressionReason($candidate->personId, $candidate->channel) ?? $this->guardReason($campaign->id);
                if (null !== $reason) {
                    $this->records->save($candidate->changeStatus('suppressed', $reason));
                    continue;
                }

                return $this->save('deliveries', $candidate->changeStatus('sending'));
            }

            return null;
        });
        if (null === $delivery) {
            return false;
        }
        $person = $this->people->get($delivery->personId);
        $destination = $person['email' === $delivery->channel ? 'email' : 'phone'] ?? '';
        try {
            $this->gateway->send($delivery->id, $delivery->channel, $destination, $delivery->subject, $delivery->body);
            $status = 'delivered';
            $error = null;
        } catch (Throwable) {
            // Transport exceptions can contain credentials or recipient details.
            $status = 'failed';
            $error = 'Delivery failed or its outcome is unknown. Check the provider before creating a replacement campaign.';
        }
        $this->transactions->run(function () use ($delivery, $status, $error) {
            $current = $this->required('deliveries', $delivery->id);
            if ('sending' === $current->status && $current->version === $delivery->version) {
                $this->records->save($current->changeStatus($status, $error));
            }
        });

        return true;
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

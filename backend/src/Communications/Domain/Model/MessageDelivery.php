<?php

declare(strict_types=1);

namespace App\Communications\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\Clock\Clock;

final class MessageDelivery extends WorkflowRecord
{
    public function __construct(
        public readonly string $campaignId,
        public readonly string $personId,
        public readonly string $channel,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $status = 'pending',
        public readonly ?string $error = null,
        public readonly ?string $deliveredAt = null,
        public readonly ?string $attemptedAt = null,
        public readonly array $resolutions = [],
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($campaignId);
        Input::id($personId);
        if (!in_array($status, ['pending', 'sending', 'delivered', 'failed', 'suppressed'], true)) {
            throw new InvalidArgumentException('Invalid delivery status.');
        }
    }

    public function resolve(string $status, string $note): self
    {
        if (!in_array($this->status, ['sending', 'failed'], true)) {
            throw new DomainException('Only uncertain or failed deliveries can be reconciled.');
        }
        if ('sending' === $this->status && null !== $this->attemptedAt && Clock::get()->now() < Input::instant($this->attemptedAt)->modify('+5 minutes')) {
            throw new DomainException('This delivery may still be in flight. Wait at least five minutes and verify its provider outcome.');
        }
        if (!in_array($status, ['delivered', 'failed'], true)) {
            throw new InvalidArgumentException('Invalid reconciled outcome.');
        }
        Input::text($note, 500);
        $resolved = $this->changeStatus($status, 'failed' === $status ? 'Provider outcome reconciled: '.$note : null);

        return $resolved->changed(['resolutions' => [...$this->resolutions, ['status' => $status, 'note' => $note, 'resolvedAt' => Clock::get()->now()->format(DATE_ATOM)]]]);
    }

    public function changeStatus(string $status, ?string $error = null): self
    {
        return $this->changed(['status' => $status, 'attemptedAt' => 'sending' === $status ? Clock::get()->now()->format(DATE_ATOM) : $this->attemptedAt, 'error' => $error, 'deliveredAt' => 'delivered' === $status ? (new DateTimeImmutable())->format(DATE_ATOM) : $this->deliveredAt]);
    }
}

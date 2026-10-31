<?php

declare(strict_types=1);

namespace App\Payments\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use InvalidArgumentException;

final class PaymentOrder extends WorkflowRecord
{
    public function __construct(
        public readonly string $personId, public readonly string $provider, public readonly string $mode,
        public readonly string $purpose, public readonly int $amountMinor, public readonly string $targetId,
        public readonly string $requestKey, public readonly string $expiresAt, public readonly bool $recurring = false,
        public readonly string $currency = 'ZAR', public readonly string $status = 'created',
        public readonly ?string $checkoutId = null, public readonly ?array $checkout = null,
        public readonly ?string $encryptedToken = null, public readonly string $subscriptionStatus = 'pending',
        public readonly ?string $reviewReason = null, public readonly ?string $reconciliationNote = null,
        string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($personId);
        Input::id($targetId);
        Input::text($requestKey, 100);
        if (!in_array($provider, ['payfast', 'yoco'], true) || !in_array($mode, ['test', 'live'], true) || !in_array($purpose, ['giving', 'ticket'], true) || 'ZAR' !== $currency || $amountMinor < 500 || $amountMinor > 100000000) {
            throw new InvalidArgumentException('Payments must be ZAR 5.00 to 1,000,000.00 through Payfast or Yoco.');
        }
        if ($recurring && ('payfast' !== $provider || 'giving' !== $purpose)) {
            throw new InvalidArgumentException('Monthly recurring giving is available through Payfast only.');
        }
        if (!in_array($status, ['created', 'ready', 'paid', 'failed', 'expired', 'review'], true) || !in_array($subscriptionStatus, ['pending', 'active', 'paused', 'locked', 'cancel_requested', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid payment status.');
        }
        new DateTimeImmutable($expiresAt);
    }

    public function change(array $changes): self
    {
        return $this->changed($changes);
    }
}

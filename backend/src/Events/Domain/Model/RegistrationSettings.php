<?php

declare(strict_types=1);

namespace App\Events\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class RegistrationSettings extends WorkflowRecord
{
    public function __construct(public readonly string $occurrenceId, public readonly bool $open = false, public readonly ?int $capacity = null, public readonly bool $waitlistEnabled = true, public readonly ?int $ticketPriceMinor = null, string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct('' === $id ? $occurrenceId : $id, $createdAt, $updatedAt, $version);
        Input::id($occurrenceId);
        if (null !== $ticketPriceMinor && ($ticketPriceMinor < 500 || $ticketPriceMinor > 100000000)) {
            throw new InvalidArgumentException('Ticket price must be ZAR 5.00 to 1,000,000.00.');
        }
        if (null !== $capacity && ($capacity < 1 || $capacity > 1000)) {
            throw new InvalidArgumentException('Capacity must be between 1 and 1000, or unlimited.');
        }
    }

    public function ticketPrice(?int $amount): self
    {
        return $this->changed(['ticketPriceMinor' => $amount]);
    }

    public function configure(bool $open, ?int $capacity, bool $waitlistEnabled): self
    {
        return $this->changed(compact('open', 'capacity', 'waitlistEnabled'));
    }
}

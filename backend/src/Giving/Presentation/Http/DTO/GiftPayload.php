<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GiftPayload
{
    public function __construct(public int $amountMinor, public string $currency, public array $allocations, public string $receivedOn, #[Assert\Choice(choices: ['cash', 'bank', 'other'])] public string $method, #[Assert\NotBlank] public string $idempotencyKey, public ?string $donorId = null, public ?string $occurrenceId = null, public ?string $externalReference = null)
    {
    }
}

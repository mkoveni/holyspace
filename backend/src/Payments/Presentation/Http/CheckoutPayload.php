<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckoutPayload
{
    public function __construct(#[Assert\Choice(choices: ['payfast', 'yoco'])] public string $provider, #[Assert\Choice(choices: ['giving', 'ticket'])] public string $purpose, #[Assert\Uuid] public string $targetId, #[Assert\NotBlank, Assert\Length(max: 100)] public string $requestKey, #[Assert\Range(min: 500, max: 100000000)] public ?int $amountMinor = null, public bool $recurring = false, #[Assert\Choice(choices: ['ZAR'])] public string $currency = 'ZAR')
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class BatchPayload
{
    public function __construct(public string $reference, #[Assert\All(constraints: [new Assert\Type('string'), new Assert\Uuid()])] public array $giftIds, public int $expectedAmountMinor, public string $currency, public string $reconciledOn)
    {
    }
}

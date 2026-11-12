<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\GetGuestPayment;

use SensitiveParameter;

final readonly class GetGuestPaymentQuery
{
    public function __construct(#[SensitiveParameter] public string $accessToken)
    {
    }
}

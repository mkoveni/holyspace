<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

/** Proof that a public request comes from a person (a captcha token), checked before any order is created. */
interface HumanCheck
{
    public function passes(?string $token, string $clientAddress): bool;
}

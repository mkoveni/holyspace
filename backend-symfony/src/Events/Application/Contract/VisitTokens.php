<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

/** Issues and verifies the secret in an occurrence's QR link. A token proves which occurrence and link generation. */
interface VisitTokens
{
    public function issue(string $occurrenceId, int $generation): string;

    /** @return VisitClaim|null null for anything that is not an authentic token */
    public function verify(string $token): ?VisitClaim;
}

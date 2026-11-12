<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class VisitLinkView
{
    /**
     * @param string|null $token    the secret for the QR link; null while the link is off
     * @param bool        $messages whether staff approved automatic welcome and follow-up emails
     */
    public function __construct(public string $occurrenceId, public bool $enabled, public ?string $token, public bool $messages = false)
    {
    }
}

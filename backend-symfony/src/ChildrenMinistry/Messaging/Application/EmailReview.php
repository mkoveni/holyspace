<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

use App\Communications\Application\Contract\TransportConfiguration;

final readonly class EmailReview
{
    /** @param list<EmailRecipient> $recipients */
    public function __construct(public string $communicationId, public int $version, public string $message, public array $recipients, public TransportConfiguration $transport, public string $subject, public string $html, public string $templateKey, public int $templateVersion)
    {
    }

    public function __debugInfo(): array
    {
        return ['communicationId' => $this->communicationId];
    }
}

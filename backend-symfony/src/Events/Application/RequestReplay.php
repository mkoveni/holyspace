<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Events\Application\Contract\EventJournalReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;

/** Client request ids make creates repeatable: the same id and hash returns the original target. */
final readonly class RequestReplay
{
    public function __construct(private EventJournalReader $requests)
    {
    }

    public function previous(string $requestId, string $kind, string $hash): ?string
    {
        EventRules::id($requestId);
        $old = $this->requests->request($requestId);
        if (null === $old) {
            return null;
        }
        if ($old->kind !== $kind || $old->hash !== $hash) {
            throw EventException::conflict('This request identifier was already used for different details.');
        }

        return $old->targetId;
    }
}

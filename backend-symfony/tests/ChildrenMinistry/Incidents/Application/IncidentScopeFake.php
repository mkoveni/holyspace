<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Incidents\Application;

use App\ChildrenMinistry\Incidents\Application\Contract\IncidentSessionScope;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Tests\ChildrenMinistry\Shared\CallLog;

/** Staff are allowed on the sessions listed. */
final readonly class IncidentScopeFake implements IncidentSessionScope
{
    /** @param list<string> $allowed */
    public function __construct(private CallLog $log, private array $allowed)
    {
    }

    public function require(string $actorId, string $sessionId): void
    {
        $this->log->entries[] = 'scope';
        if (!\in_array($sessionId, $this->allowed, true)) {
            throw ChildrenException::forbidden();
        }
    }
}

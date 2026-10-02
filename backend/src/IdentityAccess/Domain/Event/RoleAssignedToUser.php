<?php

declare(strict_types=1);
namespace App\IdentityAccess\Domain\Event;
use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;
final readonly class RoleAssignedToUser implements DomainEvent
{
    public function __construct(public string $userId, public string $roleId, public DateTimeImmutable $occurredAt) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}

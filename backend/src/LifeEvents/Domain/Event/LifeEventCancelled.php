<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Event;
use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;
final readonly class LifeEventCancelled implements DomainEvent { public function __construct(public string $lifeEventId, public DateTimeImmutable $occurredAt){} public function occurredAt(): DateTimeImmutable{return $this->occurredAt;} }

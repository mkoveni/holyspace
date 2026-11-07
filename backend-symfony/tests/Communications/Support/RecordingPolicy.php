<?php

declare(strict_types=1);

namespace App\Tests\Communications\Support;

use App\Communications\Application\Contract\DeliveryEligibility;
use App\Communications\Application\Contract\DeliverySnapshot;
use DateTimeImmutable;

/** A source context's policy that records what it was asked and can be switched to refuse. */
final class RecordingPolicy implements DeliveryEligibility
{
    public bool $eligible = true;

    /** @var list<string> */
    public array $authorized = [];

    public function __construct(private readonly string $source = 'children-ministry')
    {
    }

    public function sourceContext(): string
    {
        return $this->source;
    }

    public function authorize(DeliverySnapshot $delivery, string $actor, string $operation): void
    {
        $this->authorized[] = $operation;
    }

    public function eligible(DeliverySnapshot $delivery, DateTimeImmutable $at): bool
    {
        return $this->eligible;
    }
}

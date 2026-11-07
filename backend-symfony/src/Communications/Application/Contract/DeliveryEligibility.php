<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

use DateTimeImmutable;
use Throwable;

/** Implemented by each originating context: it revalidates its own eligibility and delegated approval. */
interface DeliveryEligibility
{
    /** Names the context whose deliveries this policy judges, for example children-ministry. */
    public function sourceContext(): string;

    /** @throws Throwable when the actor may not perform the operation on this delivery */
    public function authorize(DeliverySnapshot $delivery, string $actor, string $operation): void;

    public function eligible(DeliverySnapshot $delivery, DateTimeImmutable $at): bool;
}

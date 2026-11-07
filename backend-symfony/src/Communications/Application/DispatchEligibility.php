<?php

declare(strict_types=1);

namespace App\Communications\Application;

use App\Communications\Application\Contract\DeliveryEligibility;
use App\Communications\Application\Contract\DeliverySources;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\EmailDelivery;
use DateTimeImmutable;

/** Asks the delivery's own source context whether the actor may act and whether sending is still allowed. */
final readonly class DispatchEligibility
{
    /** @var list<DeliveryEligibility> */
    private array $policies;

    /** @param iterable<DeliveryEligibility> $policies */
    public function __construct(private DeliverySources $sources, iterable $policies)
    {
        $this->policies = array_values([...$policies]);
    }

    public function authorize(EmailDelivery $delivery, string $actor, string $operation): void
    {
        $source = $this->sources->context($delivery->id);
        foreach ($this->policies as $policy) {
            if ($policy->sourceContext() === $source) {
                $policy->authorize(DeliveryMapping::snapshot($delivery), $actor, $operation);

                return;
            }
        }

        throw CommunicationException::forbidden();
    }

    public function require(EmailDelivery $delivery, DateTimeImmutable $at): void
    {
        $source = $this->sources->context($delivery->id);
        $matching = array_values(array_filter($this->policies, static fn (DeliveryEligibility $policy): bool => $policy->sourceContext() === $source));
        if (1 !== \count($matching) || !$matching[0]->eligible(DeliveryMapping::snapshot($delivery), $at)) {
            throw CommunicationException::conflict('Sender, recipient or source eligibility changed. Review before retrying.');
        }
    }
}

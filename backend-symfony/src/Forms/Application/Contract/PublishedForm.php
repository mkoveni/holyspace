<?php

declare(strict_types=1);

namespace App\Forms\Application\Contract;

use DateTimeImmutable;

/** A published form as respondents may meet it. `definition` is the stored shape (with any answer key): project it before serving. */
final readonly class PublishedForm
{
    /**
     * @param list<string>         $groupIds
     * @param array<string, mixed> $definition
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $kind,
        public string $audience,
        public string $identity,
        public int $version,
        public array $definition,
        public array $groupIds,
        public ?DateTimeImmutable $opensAt,
        public ?DateTimeImmutable $closesAt,
        public ?int $maxResponses,
        public int $responseCount,
        public int $retentionMonths,
        public bool $leaderboardEnabled,
    ) {
    }

    /** Whether answers are accepted at this instant. */
    public function acceptsAt(DateTimeImmutable $now): bool
    {
        return (null === $this->closesAt || $now < $this->closesAt)
            && (null === $this->maxResponses || $this->responseCount < $this->maxResponses);
    }
}

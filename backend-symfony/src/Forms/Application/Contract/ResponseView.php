<?php

declare(strict_types=1);

namespace App\Forms\Application\Contract;

use DateTimeImmutable;

/** One response for staff. An anonymous response has no respondent and no time of day; an identified one names the person. */
final readonly class ResponseView
{
    /** @param array<string, mixed> $answers */
    public function __construct(
        public string $id,
        public int $formVersion,
        public DateTimeImmutable $submittedOn,
        public ?DateTimeImmutable $submittedAt,
        public ?string $personId,
        public ?string $nickname,
        public ?int $score,
        public ?int $maxScore,
        public bool $hidden,
        public array $answers,
    ) {
    }
}

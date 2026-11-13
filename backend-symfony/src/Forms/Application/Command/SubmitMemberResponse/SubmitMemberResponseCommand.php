<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\SubmitMemberResponse;

use App\Forms\Application\SubmitResult;
use App\Shared\Application\Command;

/** @implements Command<SubmitResult> */
final readonly class SubmitMemberResponseCommand implements Command
{
    /** @param array<mixed> $answers */
    public function __construct(
        public string $actorId,
        public string $formId,
        public int $formVersion,
        public string $submissionKey,
        public array $answers,
        public ?string $nickname,
    ) {
    }
}

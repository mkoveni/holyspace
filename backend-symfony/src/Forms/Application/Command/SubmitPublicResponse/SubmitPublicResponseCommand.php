<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\SubmitPublicResponse;

use App\Forms\Application\SubmitResult;
use App\Shared\Application\Command;
use SensitiveParameter;

/**
 * @implements Command<SubmitResult>
 */
final readonly class SubmitPublicResponseCommand implements Command
{
    /** @param array<mixed> $answers */
    public function __construct(
        #[SensitiveParameter] public string $token,
        public int $formVersion,
        public string $challenge,
        public string $submissionKey,
        public array $answers,
        public ?string $nickname,
    ) {
    }
}

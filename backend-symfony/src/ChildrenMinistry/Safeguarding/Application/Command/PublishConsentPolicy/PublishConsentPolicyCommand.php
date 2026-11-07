<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\PublishConsentPolicy;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class PublishConsentPolicyCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $programmeId,
        public int $expectedVersion,
        public ?string $label,
        public ?string $documentReference,
        public ?string $contentHash,
    ) {
    }
}

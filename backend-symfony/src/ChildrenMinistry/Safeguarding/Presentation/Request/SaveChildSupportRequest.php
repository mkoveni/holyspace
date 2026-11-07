<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SaveChildSupportRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $childId = null,
        #[Assert\Length(max: 2000)]
        public ?string $allergies = null,
        #[Assert\Length(max: 2000)]
        public ?string $careInstructions = null,
        #[Assert\Length(max: 2000)]
        public ?string $restrictedNotes = null,
        public string $reviewOn = '',
        public string $retainUntil = '',
    ) {
    }

    /** @return array<string, string> never dumps the care details */
    public function __debugInfo(): array
    {
        return ['redacted' => 'SaveChildSupportRequest'];
    }
}

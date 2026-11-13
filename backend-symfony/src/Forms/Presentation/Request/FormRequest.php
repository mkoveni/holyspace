<?php

declare(strict_types=1);

namespace App\Forms\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A form's editable fields. `requestId` is required to create; `expectedVersion` is required to update. The controller
 * answers 400 when the one it needs is missing.
 */
final readonly class FormRequest
{
    /**
     * @param list<string>|null    $groupIds
     * @param array<string, mixed> $definition
     */
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(max: 160)] public ?string $title = null,
        #[Assert\Length(max: 2000)] public ?string $description = null,
        #[Assert\Choice(choices: ['DATA_COLLECTION', 'QUIZ'])] public string $kind = 'DATA_COLLECTION',
        #[Assert\Choice(choices: ['PUBLIC', 'MEMBERS', 'STAFF', 'GROUPS'])] public string $audience = 'PUBLIC',
        #[Assert\Choice(choices: ['ANONYMOUS', 'IDENTIFIED'])] public string $identity = 'ANONYMOUS',
        public ?string $opensAt = null,
        public ?string $closesAt = null,
        #[Assert\Positive] public ?int $maxResponses = null,
        #[Assert\Range(min: 1, max: 3)] public int $retentionMonths = 3,
        public bool $leaderboardEnabled = false,
        #[Assert\Range(min: 1, max: 100)] public int $leaderboardSize = 10,
        public bool $showScore = false,
        public bool $showAnswers = false,
        #[Assert\All([new Assert\Uuid()])] #[Assert\Count(max: 50)] public ?array $groupIds = null,
        #[Assert\NotNull] public ?array $definition = null,
        #[Assert\Uuid] public ?string $requestId = null,
        #[Assert\PositiveOrZero] public ?int $expectedVersion = null,
    ) {
    }
}

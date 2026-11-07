<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use App\ChildrenMinistry\Sessions\Application\TeamPlanDraft;
use App\ChildrenMinistry\Sessions\Application\TeamPlanTarget;
use App\ChildrenMinistry\Sessions\Domain\StaffRole;
use App\Shared\Presentation\Json;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TeamPlanDraftRequest
{
    /** @param list<array{sessionId: string, version: int}>|null $targets */
    public function __construct(
        #[Assert\NotNull, Assert\Count(min: 1, max: 25), Assert\All([
            new Assert\NotNull(),
            new Assert\Collection(fields: [
                'sessionId' => [new Assert\NotNull(), new Assert\Uuid()],
                'version' => [new Assert\Type('int'), new Assert\PositiveOrZero()],
            ]),
        ])]
        public ?array $targets = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $workerId = null,
        #[Assert\Uuid]
        public ?string $replaceWorkerId = null,
        #[Assert\NotNull, Assert\Choice(choices: ['LEADER', 'ASSISTANT'])]
        public ?string $role = null,
    ) {
    }

    public function toDraft(): TeamPlanDraft
    {
        return new TeamPlanDraft(
            array_map(static fn (array $target): TeamPlanTarget => new TeamPlanTarget(Json::uuid($target['sessionId']), $target['version']), $this->targets ?? []),
            Json::uuid((string) $this->workerId),
            null === $this->replaceWorkerId ? null : Json::uuid($this->replaceWorkerId),
            StaffRole::from((string) $this->role),
        );
    }
}

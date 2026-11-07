<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

final readonly class MemberPushSettings
{
    /**
     * @param list<PushPreference> $preferences one per topic, defaults off at version 0
     * @param list<PushDeviceView> $devices     newest first, at most 100
     */
    public function __construct(public bool $available, public ?string $projectId, public array $preferences, public array $devices)
    {
    }
}

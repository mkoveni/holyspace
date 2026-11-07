<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** Whether the church has finished device push setup, and for which Expo project. */
interface PushSettings
{
    public function enabled(): bool;

    public function projectId(): ?string;
}

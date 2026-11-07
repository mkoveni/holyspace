<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

interface PushReader
{
    /** @return list<PushDeviceView> the account's registrations, flagging those bound to the given session */
    public function devices(string $account, string $session): array;

    /** @return list<PushPreference> */
    public function preferences(string $account): array;
}

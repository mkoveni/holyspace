<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\RevokeMyPushDevice;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RevokeMyPushDeviceCommand implements Command
{
    public function __construct(public string $deviceId)
    {
    }
}

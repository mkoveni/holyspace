<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\RevokeMyPushDevice;

use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\PushDevice;
use App\Communications\Domain\Repository\PushDeviceRepository;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Disables one of the caller's own registrations, from any of their devices. Another account's device is a 404. */
final readonly class RevokeMyPushDeviceHandler implements CommandHandler
{
    public function __construct(
        private AuthenticatedAccount $account,
        private PushDeviceRepository $devices,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RevokeMyPushDeviceCommand $command): void
    {
        $account = $this->account->requireAccount();
        $device = $this->devices->lock($command->deviceId);
        if (null === $device || $device->accountId !== $account) {
            throw CommunicationException::missing('Push device');
        }
        $this->account->requireAccount();
        if (PushDevice::REVOKED !== $device->status) {
            $this->devices->save($device->revoke(PushDevice::REVOKED, $this->clock->now()));
        }
        $this->account->requireAccount();
    }
}

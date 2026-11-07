<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListMySessions;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\CurrentActor;
use App\IdentityAccess\Application\Contract\DeviceSession;
use App\IdentityAccess\Application\Contract\DeviceSessionReader;
use App\IdentityAccess\Application\DeviceSessionPage;
use App\IdentityAccess\Application\DeviceSessionView;
use App\IdentityAccess\Domain\IdentityException;
use Psr\Clock\ClockInterface;

final readonly class ListMySessionsHandler
{
    public function __construct(
        private Authorization $authorization,
        private CurrentActor $current,
        private DeviceSessionReader $reader,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ListMySessionsQuery $query): DeviceSessionPage
    {
        $accountId = $this->authorization->requireAccount();
        if ($query->limit < 1 || $query->limit > 100 || $query->offset < 0 || $query->offset > 100000) {
            throw IdentityException::invalid('Choose a valid device page.');
        }
        $currentSession = $this->current->current()?->sessionId;

        $rows = $this->reader->devices($accountId, $this->clock->now(), $query->limit + 1, $query->offset);

        return new DeviceSessionPage(
            array_map(
                static fn (DeviceSession $device): DeviceSessionView => new DeviceSessionView($device->id, $device->deviceName, $device->createdAt, $device->expiresAt, $device->id === $currentSession),
                array_slice($rows, 0, $query->limit),
            ),
            count($rows) > $query->limit,
        );
    }
}

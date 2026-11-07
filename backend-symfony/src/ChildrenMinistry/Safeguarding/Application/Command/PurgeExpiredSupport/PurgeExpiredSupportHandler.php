<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\PurgeExpiredSupport;

use App\ChildrenMinistry\Safeguarding\Application\Contract\SupportReader;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SupportRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class PurgeExpiredSupportHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private SupportReader $support,
        private SupportRepository $repository,
        private ParticipantAccess $participants,
        private ChurchCalendar $calendar,
    ) {
    }

    /** @return int how many records were deleted */
    public function handle(PurgeExpiredSupportCommand $command): int
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.support');
        $this->authorization->requireActorPermission($command->actorId, 'children.supervise');

        ChildrenRules::page($command->limit, 0);
        $now = $this->clock->now();
        $today = $this->calendar->today();
        $count = 0;
        foreach ($this->support->expiredSupport($today, $command->limit) as $childId) {
            $this->participants->lockChild($childId);
            // Re-check under the lock: the record may have been renewed since it was listed.
            $record = $this->repository->supportRecordForUpdate($childId);
            if (null === $record || !$record->expiredOn($today)) {
                continue;
            }
            $this->repository->purge($childId, $today);
            ++$count;
            $this->audit->record($command->actorId, 'support.purged', $childId, 'Retention expired', $now);
        }

        return $count;
    }
}

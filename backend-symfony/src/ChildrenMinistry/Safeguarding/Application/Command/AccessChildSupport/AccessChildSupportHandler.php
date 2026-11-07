<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\AccessChildSupport;

use App\ChildrenMinistry\Safeguarding\Application\Contract\SupportReader;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SupportSessionScope;
use App\ChildrenMinistry\Safeguarding\Application\SupportRecordView;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** The only way to read a support record. It is a command because every read is audited in the same transaction. */
final readonly class AccessChildSupportHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private SupportReader $support,
        private SupportSessionScope $scope,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(AccessChildSupportCommand $command): SupportRecordView
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.support');
        if (null === $command->sessionId) {
            $this->authorization->requireActorPermission($command->actorId, 'children.manage');
        } else {
            $this->scope->require($command->actorId, $command->sessionId, $command->childId);
        }

        $now = $this->clock->now();
        $record = $this->support->supportRecord($command->childId);
        if (null === $record || $record->retainUntil->isBefore($this->calendar->today())) {
            throw ChildrenException::missing('Current support record');
        }
        $this->audit->record($command->actorId, 'support.accessed', $command->childId, '', $now);

        return $record;
    }
}

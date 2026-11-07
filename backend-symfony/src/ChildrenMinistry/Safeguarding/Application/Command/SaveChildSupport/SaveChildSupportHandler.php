<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\SaveChildSupport;

use App\ChildrenMinistry\Safeguarding\Domain\Repository\SupportRepository;
use App\ChildrenMinistry\Safeguarding\Domain\SupportRecord;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class SaveChildSupportHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private SupportRepository $repository,
        private ParticipantAccess $participants,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(SaveChildSupportCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');
        $this->authorization->requireActorPermission($command->actorId, 'children.support');

        $this->participants->lockChild($command->childId);
        $now = $this->clock->now();
        $today = $this->calendar->today();
        ChildrenRules::child($this->participants->available($command->childId)->birthDate, $today);
        $existing = $this->repository->supportRecordForUpdate($command->childId);
        if ($command->reviewOn->isBefore($today) || $command->retainUntil->isBefore($today)) {
            throw ChildrenException::invalid('Review and retention dates must not be in the past.');
        }
        if (null === $existing) {
            ChildrenRules::version(0, $command->expectedVersion);
            $this->repository->insertSupportRecord(SupportRecord::create($command->childId, $command->allergies, $command->careInstructions, $command->restrictedNotes, $command->reviewOn, $command->retainUntil, $now));
        } else {
            $this->repository->updateSupportRecord($existing->revise($command->expectedVersion, $command->allergies, $command->careInstructions, $command->restrictedNotes, $command->reviewOn, $command->retainUntil, $now));
        }
        $this->audit->record($command->actorId, 'support.updated', $command->childId, '', $now);
    }
}

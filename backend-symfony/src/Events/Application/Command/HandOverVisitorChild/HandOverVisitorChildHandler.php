<?php

declare(strict_types=1);

namespace App\Events\Application\Command\HandOverVisitorChild;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\VisitorChildRepository;
use App\Events\Domain\VisitorChild;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Welcome-team staff mark a child named by a visitor as handed to Children's Ministry (who enrol the child
 * in their own system) or dismissed. Nothing is created in Children's Ministry from here.
 */
final readonly class HandOverVisitorChildHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private VisitorChildRepository $children,
        private EventJournal $journal,
        private ClockInterface $clock,
    ) {
    }

    public function handle(HandOverVisitorChildCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.attendance.manage');
        $decision = VisitorChild::allowedDecision($command->decision);
        $childId = EventRules::id($command->childId);
        $state = $this->children->state($childId) ?? throw EventException::missing('Visitor child');
        if ($state['status'] === $decision) {
            return;
        }
        if (VisitorChild::PENDING !== $state['status']) {
            throw EventException::conflict('This child was already settled as '.strtolower(str_replace('_', ' ', $state['status'])).'.');
        }
        if ($state['visitRemoved']) {
            throw EventException::conflict('The visitor who named this child was removed.');
        }
        $now = $this->clock->now();
        if (!$this->children->settle($childId, $decision, $command->actorId, $now)) {
            throw EventException::conflict('This child was settled by someone else.');
        }
        $this->journal->audit($command->actorId, 'visitor.child.'.strtolower($decision), $childId, '', $now);
    }
}

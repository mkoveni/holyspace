<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\IssueMyChildPickupLabel;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Sessions\Application\Contract\AttendanceReader;
use App\ChildrenMinistry\Sessions\Application\Contract\PickupTokens;
use App\ChildrenMinistry\Sessions\Application\LabelIssue;
use App\ChildrenMinistry\Sessions\Application\MemberFamilyViews;
use App\ChildrenMinistry\Sessions\Domain\AttendanceEvent;
use App\ChildrenMinistry\Sessions\Domain\Repository\AttendanceRepository;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\MemberIdentity;
use App\IdentityAccess\Application\Contract\MemberIdentityGuard;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * A guardian replaces their child's collection QR: five minutes, hashed, and never longer than their own authority and
 * pickup grant. Lock order: member link, then the people involved and the child guard, then the session, then the visit.
 * Staff still verify identity and live pickup rights at collection; the label alone is not authority.
 */
final readonly class IssueMyChildPickupLabelHandler implements CommandHandler
{
    private const int LIFETIME_SECONDS = 300;

    public function __construct(
        private MemberIdentity $identity,
        private MemberIdentityGuard $guard,
        private MemberFamilyViews $views,
        private ParticipantAccess $participants,
        private AttendanceReader $attendance,
        private AttendanceRepository $repository,
        private SessionRepository $sessions,
        private ParticipationReader $participation,
        private SafeguardingReader $safeguarding,
        private PickupTokens $tokens,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    public function handle(IssueMyChildPickupLabelCommand $command): LabelIssue
    {
        $guardian = $this->identity->requirePerson($command->actorId);
        $this->views->require($guardian, $command->childId);
        if (!$command->replacePrevious) {
            throw ChildrenException::invalid('Confirm that any previous collection label will be replaced.');
        }
        $source = $this->attendance->attendance($command->attendanceId);
        if (null === $source || $source->childId !== $command->childId) {
            throw ChildrenException::missing('Attendance');
        }

        if ($this->guard->lockPerson($command->actorId) !== $guardian) {
            throw ChildrenException::forbidden();
        }
        $this->participants->lockChild($command->childId, $guardian);
        $session = $this->sessions->ministrySessionForUpdate($source->sessionId) ?? throw ChildrenException::missing('Session');
        $this->identity->requirePerson($command->actorId);
        $this->views->require($guardian, $command->childId);
        $current = $this->repository->attendanceForUpdate($command->attendanceId) ?? throw ChildrenException::missing('Attendance');
        if ($current->sessionId !== $session->id || $current->childId !== $command->childId) {
            throw ChildrenException::conflict('Attendance moved. Refresh before requesting a label.');
        }
        $current->requireOpen();

        $now = $this->clock->now();
        $programme = ($this->participation->ministryClass($session->classId) ?? throw ChildrenException::missing('Class'))->programmeId;
        $grant = null;
        $grantAuthority = null;
        foreach ($this->safeguarding->validAuthorizations($programme, $command->childId, $guardian, $now) as $candidate) {
            $authority = $this->safeguarding->guardianAuthority($candidate->authorityId);
            if (null !== $authority && null === $authority->revokedAt && ChildrenRules::covers($authority->validFrom, $authority->validUntil, $now, $now)) {
                $grant = $candidate;
                $grantAuthority = $authority;
                break;
            }
        }
        if (null === $grant || null === $grantAuthority) {
            throw ChildrenException::conflict('Your own current pickup authorization is required.');
        }
        $own = null;
        foreach ($this->safeguarding->authorities($command->childId) as $authority) {
            if ($authority->guardianId === $guardian && null === $authority->revokedAt && ChildrenRules::covers($authority->validFrom, $authority->validUntil, $now, $now) && (null === $own || $authority->validUntil > $own->validUntil)) {
                $own = $authority;
            }
        }
        if (null === $own) {
            throw ChildrenException::forbidden();
        }
        $expires = min($now->modify('+'.self::LIFETIME_SECONDS.' seconds'), $own->validUntil, $grant->validUntil, $grantAuthority->validUntil);

        $code = $this->tokens->issue();
        $reason = 'Member requested a replacement collection QR.';
        $updated = $current->issueLabel($this->tokens->hash($code), $expires, $command->expectedVersion, $reason);
        $this->repository->updateAttendance($updated);
        $this->repository->insertAttendanceEvent(new AttendanceEvent($this->ids->uuid(), $current->id, $command->actorId, 'LABEL_ISSUED', $reason, $now));
        $this->audit->record($command->actorId, 'pickup.member_label.issued', $current->id, $reason, $now);

        return new LabelIssue($current->id, $updated->labelVersion, $code, $expires, $updated->version);
    }
}

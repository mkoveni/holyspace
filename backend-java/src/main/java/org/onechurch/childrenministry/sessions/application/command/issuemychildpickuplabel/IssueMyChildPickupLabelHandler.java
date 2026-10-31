package org.onechurch.childrenministry.sessions.application.command.issuemychildpickuplabel;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.PickupAuthorization;
import org.onechurch.childrenministry.sessions.application.LabelIssue;
import org.onechurch.childrenministry.sessions.application.MemberFamilyViews;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.application.contract.PickupTokens;
import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.repository.AttendanceRepository;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.identityaccess.application.contract.MemberIdentityGuard;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.Comparator;
import java.util.UUID;
public final class IssueMyChildPickupLabelHandler {
    private final AuthenticatedAccount account;
    private final MemberIdentity identity;
    private final MemberIdentityGuard guard;
    private final MemberFamilyViews views;
    private final ParticipantAccess participants;
    private final AttendanceReader attendance;
    private final AttendanceRepository repository;
    private final SessionRepository sessions;
    private final ParticipationReader participation;
    private final SafeguardingReader safeguarding;
    private final PickupTokens tokens;
    private final ChildrenAudit audit;
    private final UnitOfWork tx;
    private final Clock clock;
    public IssueMyChildPickupLabelHandler(AuthenticatedAccount account, MemberIdentity identity,
        MemberIdentityGuard guard, MemberFamilyViews views, ParticipantAccess participants, AttendanceReader attendance,
        AttendanceRepository repository, SessionRepository sessions, ParticipationReader participation,
        SafeguardingReader safeguarding, PickupTokens tokens, ChildrenAudit audit, UnitOfWork tx, Clock clock) {
        this.account = account;
        this.identity = identity;
        this.guard = guard;
        this.views = views;
        this.participants = participants;
        this.attendance = attendance;
        this.repository = repository;
        this.sessions = sessions;
        this.participation = participation;
        this.safeguarding = safeguarding;
        this.tokens = tokens;
        this.audit = audit;
        this.tx = tx;
        this.clock = clock;
    }
    public LabelIssue handle(IssueMyChildPickupLabelCommand c) {
        UUID actor = account.requireAccount();
        UUID guardian = identity.requirePerson(actor);
        views.require(guardian, c.childId());
        if (!c.replacePrevious()) {
            throw ChildrenException.invalid("Confirm that any previous collection label will be replaced.");
        }
        Attendance source = attendance.attendance(c.attendanceId())
            .filter(a -> a.childId().equals(c.childId()))
            .orElseThrow(() -> ChildrenException.missing("Attendance"));
        return tx.execute(() -> {
            if (!guard.lockPerson(actor).equals(guardian)) {
                throw ChildrenException.forbidden();
            }
            participants.lockChild(c.childId(), guardian);
            MinistrySession session = sessions.ministrySessionForUpdate(source.sessionId())
                .orElseThrow(() -> ChildrenException.missing("Session"));
            identity.requirePerson(actor);
            views.require(guardian, c.childId());
            Attendance current = repository.attendanceForUpdate(c.attendanceId())
                .orElseThrow(() -> ChildrenException.missing("Attendance"));
            if (!current.sessionId().equals(session.id()) || !current.childId().equals(c.childId())) {
                throw ChildrenException.conflict("Attendance moved. Refresh before requesting a label.");
            }
            current.requireOpen();
            Instant now = clock.instant();
            UUID programme = participation.ministryClass(session.classId()).orElseThrow().programmeId();
            PickupAuthorization grant = safeguarding.validAuthorizations(programme, c.childId(), guardian, now)
                .stream()
                .filter(
                    g -> safeguarding.guardianAuthority(g.authorityId()).filter(a -> a.covers(now, now)).isPresent())
                .findFirst()
                .orElseThrow(() -> ChildrenException.conflict("Your own current pickup authorization is required."));
            GuardianAuthority own = safeguarding.authorities(c.childId())
                .stream()
                .filter(a -> a.guardianId().equals(guardian) && a.covers(now, now))
                .max(Comparator.comparing(GuardianAuthority::validUntil))
                .orElseThrow(ChildrenException::forbidden);
            Instant expires = java.util.stream.Stream
                .of(now.plusSeconds(300), own.validUntil(), grant.validUntil(),
                    safeguarding.guardianAuthority(grant.authorityId()).orElseThrow().validUntil())
                .min(Comparator.naturalOrder())
                .orElseThrow();
            String code = tokens.issue();
            String reason = "Member requested a replacement collection QR.";
            Attendance updated = current.issueLabel(tokens.hash(code), expires, c.expectedVersion(), reason);
            repository.updateAttendance(updated);
            repository.insertAttendanceEvent(
                new AttendanceEvent(UUID.randomUUID(), current.id(), actor, "LABEL_ISSUED", reason, now));
            audit.record(actor, "pickup.member_label.issued", current.id(), reason, now);
            return new LabelIssue(current.id(), updated.labelVersion(), code, expires, updated.version());
        });
    }
}

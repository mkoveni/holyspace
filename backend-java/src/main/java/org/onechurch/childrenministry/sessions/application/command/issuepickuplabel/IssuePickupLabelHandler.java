package org.onechurch.childrenministry.sessions.application.command.issuepickuplabel;

import org.onechurch.childrenministry.sessions.application.LabelIssue;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.application.contract.PickupTokens;
import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.repository.AttendanceRepository;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;
public final class IssuePickupLabelHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipantAccess participants;
    private final StaffScope scope;
    private final SessionRepository sessionRepository;
    private final AttendanceReader attendance;
    private final AttendanceRepository attendanceRepository;
    private final PickupTokens tokens;
    public IssuePickupLabelHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipantAccess participants, StaffScope scope, SessionRepository sessionRepository,
        AttendanceReader attendance, AttendanceRepository attendanceRepository, PickupTokens tokens) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participants = participants;
        this.scope = scope;
        this.sessionRepository = sessionRepository;
        this.attendance = attendance;
        this.attendanceRepository = attendanceRepository;
        this.tokens = tokens;
    }
    public LabelIssue handle(IssuePickupLabelCommand command) {
        authorization.requirePermission(command.actorId(), "children.station");
        return transactions.execute(() -> {
            Attendance source = attendance.attendance(command.attendanceId())
                .orElseThrow(() -> ChildrenException.missing("Attendance"));
            participants.lockChild(source.childId());
            MinistrySession session = sessionRepository.ministrySessionForUpdate(source.sessionId())
                .orElseThrow(() -> ChildrenException.missing("MinistrySession"));
            scope.require(command.actorId(), session.id());
            Attendance value = attendanceRepository.attendanceForUpdate(command.attendanceId())
                .orElseThrow(() -> ChildrenException.missing("Attendance"));
            if (!value.sessionId().equals(session.id())) {
                throw ChildrenException.conflict("Attendance moved; reload it first.");
            }
            Instant now = clock.instant();
            String token = tokens.issue();
            Attendance updated = value.issueLabel(tokens.hash(token), now.plusSeconds(86400), command.expectedVersion(),
                command.replacementReason());
            attendanceRepository.updateAttendance(updated);
            audit.record(command.actorId(), "pickup.label.issued", value.id(),
                ChildrenRules.optional(command.replacementReason(), 500), now);
            attendanceRepository.insertAttendanceEvent(new AttendanceEvent(UUID.randomUUID(), value.id(),
                command.actorId(), "LABEL_ISSUED", ChildrenRules.optional(command.replacementReason(), 500), now));
            return new LabelIssue(value.id(), updated.labelVersion(), token, updated.labelExpiresAt(),
                updated.version());
        });
    }
}

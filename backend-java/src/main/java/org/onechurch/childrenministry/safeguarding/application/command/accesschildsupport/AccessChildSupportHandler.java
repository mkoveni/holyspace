package org.onechurch.childrenministry.safeguarding.application.command.accesschildsupport;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.safeguarding.application.contract.SupportReader;
import org.onechurch.childrenministry.safeguarding.domain.SupportRecord;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;

public final class AccessChildSupportHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final StaffScope scope;
    private final SupportReader support;
    private final AttendanceReader attendance;
    private final ParticipationReader participation;
    private final SessionReader session;
    private final SessionOperations operations;
    private final ZoneId zone;
    public AccessChildSupportHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, StaffScope scope, SupportReader support, AttendanceReader attendance,
        ParticipationReader participation, SessionReader session, SessionOperations operations, ZoneId zone) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.scope = scope;
        this.support = support;
        this.attendance = attendance;
        this.participation = participation;
        this.session = session;
        this.operations = operations;
        this.zone = zone;
    }
    public SupportRecord handle(AccessChildSupportCommand command) {
        authorization.requirePermission(command.actorId(), "children.support");
        return transactions.execute(() -> {
            if (command.sessionId() == null) {
                authorization.requirePermission(command.actorId(), "children.manage");
            } else {
                scope.require(command.actorId(), command.sessionId());
                MinistrySession session = this.session.ministrySession(command.sessionId())
                    .orElseThrow(() -> ChildrenException.missing("MinistrySession"));
                boolean enrolled =
                    participation.assignmentAt(session.classId(), command.childId(), operations.date(session))
                        .filter(value -> participation.enrolment(value.enrolmentId())
                            .filter(enrolment -> enrolment.covers(operations.date(session)))
                            .isPresent())
                        .isPresent();
                boolean attending = attendance.childAttendance(command.childId(), 100, 0)
                    .stream()
                    .anyMatch(value -> value.sessionId().equals(session.id()) && value.checkedOutAt() == null);
                if (!enrolled && !attending) {
                    throw ChildrenException.forbidden();
                }
            }
            Instant now = clock.instant();
            SupportRecord supportRecord = support.supportRecord(command.childId())
                .filter(value -> !value.retainUntil().isBefore(LocalDate.now(clock.withZone(zone))))
                .orElseThrow(() -> ChildrenException.missing("Current support record"));
            audit.record(command.actorId(), "support.accessed", command.childId(), "", now);
            return supportRecord;
        });
    }
}

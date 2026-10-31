package org.onechurch.childrenministry.sessions.application.command.setsessionstatus;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.ZoneId;

public final class SetSessionStatusHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final SessionRepository sessionRepository;
    private final SessionOperations operations;
    private final AttendanceReader attendance;
    private final IncidentReader incidents;
    private final ZoneId zone;
    public SetSessionStatusHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, SessionRepository sessionRepository, SessionOperations operations, AttendanceReader attendance,
        IncidentReader incidents, ZoneId zone) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.sessionRepository = sessionRepository;
        this.operations = operations;
        this.attendance = attendance;
        this.incidents = incidents;
        this.zone = zone;
    }
    public void handle(SetSessionStatusCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        transactions.execute(() -> {
            MinistrySession session = sessionRepository.ministrySessionForUpdate(command.sessionId())
                .orElseThrow(() -> ChildrenException.missing("MinistrySession"));
            Instant now = clock.instant();
            String reason = ChildrenRules.text(command.reason(), 500);
            if ((command.status() == SessionStatus.CLOSED || command.status() == SessionStatus.CANCELLED)
                && attendance.occupancy(session.id()) > 0) {
                throw ChildrenException.conflict("Collect or transfer all children before closing or cancelling.");
            }
            if (command.status() == SessionStatus.CLOSED
                && incidents.counts(session.id(), now.atZone(zone).toLocalDate()).unresolved() > 0) {
                authorization.requirePermission("children.incidents.manage");
                if (!command.acknowledgeIncidents()) {
                    throw ChildrenException.conflict(
                        "Review and acknowledge outstanding incident follow-ups before closing this session.");
                }
                audit.record(command.actorId(), "session.incidents.acknowledged", session.id(),
                    "Outstanding follow-up continues after closure.", now);
            }
            if (command.status() == SessionStatus.OPEN && operations.view(session, now).staffingNeedsReview()) {
                throw ChildrenException
                    .conflict("Opening requires eligible present staff, including a leader, and sufficient capacity.");
            }
            sessionRepository.updateMinistrySession(session.status(command.status(), command.expectedVersion()));
            audit.record(command.actorId(), "session.status.changed", session.id(), reason, now);
            return null;
        });
    }
}

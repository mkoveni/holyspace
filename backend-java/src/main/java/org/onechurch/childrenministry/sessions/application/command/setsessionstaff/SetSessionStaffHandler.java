package org.onechurch.childrenministry.sessions.application.command.setsessionstaff;

import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.SessionView;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStaff;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
public final class SetSessionStaffHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final SessionRepository sessionRepository;
    private final SessionReader session;
    private final SessionOperations operations;
    public SetSessionStaffHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, SessionRepository sessionRepository, SessionReader session, SessionOperations operations) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.sessionRepository = sessionRepository;
        this.session = session;
        this.operations = operations;
    }
    public void handle(SetSessionStaffCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        transactions.execute(() -> {
            MinistrySession session = sessionRepository.ministrySessionForUpdate(command.sessionId())
                .orElseThrow(() -> ChildrenException.missing("MinistrySession"));
            if (session.status() == SessionStatus.CLOSED || session.status() == SessionStatus.CANCELLED) {
                throw ChildrenException.conflict("Terminal session staffing cannot change.");
            }
            sessionRepository.workerForUpdate(command.workerId())
                .orElseThrow(() -> ChildrenException.missing("Worker"));
            if (command.present() && this.session.presentElsewhere(command.workerId(), session.id(), session.startsAt(),
                session.endsAt())) {
                throw ChildrenException
                    .conflict("Worker is already present in an overlapping session. Mark them absent there first.");
            }
            ChildrenRules.version(session.version(), command.expectedVersion());
            Instant now = clock.instant();
            sessionRepository.insertSessionStaff(
                new SessionStaff(session.id(), command.workerId(), command.role(), command.present()));
            SessionView view = operations.view(session, now);
            SessionStatus status = session.status() == SessionStatus.OPEN && view.staffingNeedsReview()
                ? SessionStatus.NEEDS_REVIEW : session.status();
            sessionRepository.updateMinistrySession(session.status(status, command.expectedVersion()));
            audit.record(command.actorId(), "session.staff.changed", session.id(),
                command.workerId() + ":" + command.role() + ":present=" + command.present(), now);
            return null;
        });
    }
}

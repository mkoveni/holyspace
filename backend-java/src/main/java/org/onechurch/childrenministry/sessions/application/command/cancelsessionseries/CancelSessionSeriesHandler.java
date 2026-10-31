package org.onechurch.childrenministry.sessions.application.command.cancelsessionseries;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionSeries;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.sessions.domain.repository.SessionSeriesRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.Comparator;
import java.util.List;
import java.util.UUID;
public final class CancelSessionSeriesHandler {
    private final PermissionChecker authorization;
    private final UnitOfWork transactions;
    private final SessionSeriesRepository series;
    private final SessionRepository sessions;
    private final AttendanceReader attendance;
    private final ChildrenAudit audit;
    private final Clock clock;
    public CancelSessionSeriesHandler(PermissionChecker authorization, UnitOfWork transactions,
        SessionSeriesRepository series, SessionRepository sessions, AttendanceReader attendance, ChildrenAudit audit,
        Clock clock) {
        this.authorization = authorization;
        this.transactions = transactions;
        this.series = series;
        this.sessions = sessions;
        this.attendance = attendance;
        this.audit = audit;
        this.clock = clock;
    }
    public void handle(CancelSessionSeriesCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        transactions.execute(() -> {
            series.lockScheduling();
            SessionSeries value =
                series.forUpdate(command.seriesId()).orElseThrow(() -> ChildrenException.missing("Series"));
            SessionSeries stopped = value.stop(command.expectedVersion());
            String reason = ChildrenRules.text(command.reason(), 500);
            List<MinistrySession> locked = series.sessionIds(value.id())
                .stream()
                .sorted(Comparator.comparing(UUID::toString))
                .map(
                    id -> sessions.ministrySessionForUpdate(id).orElseThrow(() -> ChildrenException.missing("Session")))
                .toList();
            Instant now = clock.instant();
            locked.stream()
                .filter(session -> session.status() == SessionStatus.PLANNED && session.startsAt().isAfter(now))
                .forEach(session -> {
                    if (attendance.occupancy(session.id()) > 0) {
                        throw ChildrenException.conflict("Collect all children before cancelling.");
                    }
                    sessions.updateMinistrySession(session.status(SessionStatus.CANCELLED, session.version()));
                    audit.record(command.actorId(), "session.status.changed", session.id(), reason, now);
                });
            series.update(stopped);
            audit.record(command.actorId(), "session.series.cancelled", value.id(), reason, now);
            return null;
        });
    }
}

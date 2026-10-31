package org.onechurch.childrenministry.sessions.application.command.createsessionseries;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPlanning;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPreview;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionSeries;
import org.onechurch.childrenministry.sessions.domain.SessionStaff;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.childrenministry.sessions.domain.StaffRole;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.sessions.domain.repository.SessionSeriesRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.Optional;
import java.util.UUID;
public final class CreateSessionSeriesHandler {
    private final PermissionChecker authorization;
    private final UnitOfWork transactions;
    private final SessionSeriesRepository series;
    private final SessionRepository sessions;
    private final SessionSeriesPlanning planning;
    private final ChildrenAudit audit;
    private final Clock clock;
    public CreateSessionSeriesHandler(PermissionChecker authorization, UnitOfWork transactions,
        SessionSeriesRepository series, SessionRepository sessions, SessionSeriesPlanning planning, ChildrenAudit audit,
        Clock clock) {
        this.authorization = authorization;
        this.transactions = transactions;
        this.series = series;
        this.sessions = sessions;
        this.planning = planning;
        this.audit = audit;
        this.clock = clock;
    }
    public UUID handle(CreateSessionSeriesCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        if (command.requestId() == null || command.draft() == null || command.draft().recurrence() == null
            || command.draft().name() == null) {
            throw ChildrenException.invalid("A named schedule and request ID are required.");
        }
        return transactions.execute(() -> {
            series.lockScheduling();
            String fingerprint = command.draft().fingerprint();
            Optional<SessionSeries> previous = series.request(command.actorId(), command.requestId());
            if (previous.isPresent()) {
                return previous.filter(value -> value.requestFingerprint().equals(fingerprint))
                    .map(SessionSeries::id)
                    .orElseThrow(
                        () -> ChildrenException.conflict("This request ID was already used for a different schedule."));
            }
            MinistrySession source = sessions.ministrySessionForUpdate(command.draft().sourceSessionId())
                .orElseThrow(() -> ChildrenException.missing("Source session"));
            SessionSeriesPreview preview = planning.preview(command.draft());
            if (preview.occurrences().stream().anyMatch(value -> !value.conflicts().isEmpty())) {
                throw ChildrenException.conflict(
                    "Some dates are no longer available. Refresh the preview and skip clashes before creating the series.");
            }
            SessionSeries value = new SessionSeries(UUID.randomUUID(), command.draft().name(), source.id(),
                source.classId(), source.roomId(), source.seatLimit(), preview.timeZone(), command.draft().recurrence(),
                clock.instant(), command.actorId(), command.requestId(), fingerprint, false, 0);
            series.insert(value);
            preview.occurrences().stream().filter(occurrence -> !occurrence.slot().excluded()).forEach(occurrence -> {
                UUID sessionId = UUID.randomUUID();
                sessions.insertMinistrySession(
                    new MinistrySession(sessionId, source.classId(), source.roomId(), occurrence.slot().startsAt(),
                        occurrence.slot().endsAt(), source.seatLimit(), SessionStatus.PLANNED, 0));
                series.link(value.id(), sessionId, occurrence.slot().date());
                preview.team()
                    .forEach(member -> sessions.insertSessionStaff(
                        new SessionStaff(sessionId, member.workerId(), StaffRole.valueOf(member.role()), false)));
                audit.record(command.actorId(), "session.created", sessionId, "series:" + value.id(),
                    value.createdAt());
            });
            audit.record(command.actorId(), "session.series.created", value.id(), value.name(), value.createdAt());
            return value.id();
        });
    }
}

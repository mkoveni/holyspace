package org.onechurch.childrenministry.incidents.application.command.reportincident;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.incidents.domain.Incident;
import org.onechurch.childrenministry.incidents.domain.IncidentDetails;
import org.onechurch.childrenministry.incidents.domain.IncidentEvent;
import org.onechurch.childrenministry.incidents.domain.repository.IncidentRepository;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.RequestFingerprint;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.Optional;
import java.util.UUID;
public final class ReportIncidentHandler {
    private final PermissionChecker auth;
    private final StaffScope scope;
    private final IncidentReader reader;
    private final IncidentRepository repository;
    private final SessionRepository sessions;
    private final PersonDirectory people;
    private final UnitOfWork tx;
    private final ChildrenAudit audit;
    private final Clock clock;
    private final ZoneId zone;
    public ReportIncidentHandler(PermissionChecker auth, StaffScope scope, IncidentReader reader,
        IncidentRepository repository, SessionRepository sessions, PersonDirectory people, UnitOfWork tx,
        ChildrenAudit audit, Clock clock, ZoneId zone) {
        this.auth = auth;
        this.scope = scope;
        this.reader = reader;
        this.repository = repository;
        this.sessions = sessions;
        this.people = people;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
        this.zone = zone;
    }
    public UUID handle(ReportIncidentCommand c) {
        auth.requirePermission(c.actorId(), auth.hasPermission("children.incidents.manage")
            ? "children.incidents.manage" : "children.incidents.record");
        if (c.requestId() == null || c.childId() == null || c.sessionId() == null || c.segmentId() == null) {
            throw ChildrenException.invalid("Choose a child and their session attendance.");
        }
        IncidentDetails details = new IncidentDetails(c.description(), c.immediateAction());
        String hash = RequestFingerprint.of(c.childId(), c.sessionId(), c.segmentId(), c.category(), c.severity(),
            ChildrenRules.instant(c.occurredAt()), details.description(), details.immediateAction(), c.reviewOn(),
            c.retainUntil());
        return tx.execute(() -> {
            people.lock(c.childId()).orElseThrow(() -> ChildrenException.missing("Child"));
            sessions.ministrySessionForUpdate(c.sessionId()).orElseThrow(() -> ChildrenException.missing("Session"));
            if (!auth.hasPermission("children.incidents.manage"))
                scope.require(c.actorId(), c.sessionId());
            Optional<IncidentReader.Receipt> receipt = reader.receipt(c.actorId(), c.requestId());
            if (receipt.isPresent()) {
                IncidentReader.Receipt previous = receipt.orElseThrow();
                if (!previous.requestHash().equals(hash)) {
                    throw ChildrenException.conflict("This report request was already used for different details.");
                }
                return previous.id();
            }
            Instant now = clock.instant();
            LocalDate on = now.atZone(zone).toLocalDate();
            if (c.occurredAt() == null || c.occurredAt().isAfter(now)
                || !reader.attended(c.childId(), c.sessionId(), c.segmentId(), ChildrenRules.instant(c.occurredAt()))) {
                throw ChildrenException.invalid(
                    "The incident time must fall within this child's recorded attendance in the selected session.");
            }
            if (c.reviewOn() == null || c.reviewOn().isBefore(on) || c.retainUntil() == null
                || c.retainUntil().isBefore(on)) {
                throw ChildrenException.invalid("Review and retention dates must be current or future dates.");
            }
            UUID id = UUID.randomUUID();
            Incident incident = new Incident(id, c.childId(), c.sessionId(), c.segmentId(), c.category(), c.severity(),
                c.occurredAt(), c.actorId(), now, "OPEN", null, c.reviewOn(), c.retainUntil(), 0);
            repository.insert(incident, details, c.requestId(), hash);
            repository.append(
                new IncidentEvent(UUID.randomUUID(), id, 0, "REPORTED", c.actorId(), now, "Incident recorded.", null));
            audit.record(c.actorId(), "incident.reported", id, "", now);
            return id;
        });
    }
}

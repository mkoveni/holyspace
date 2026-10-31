package org.onechurch.childrenministry.incidents.application.command.accessincident;
import org.onechurch.childrenministry.incidents.application.IncidentAccess;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.incidents.domain.Incident;
import org.onechurch.childrenministry.incidents.domain.IncidentDetails;
import org.onechurch.childrenministry.incidents.domain.IncidentEvent;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.ZoneId;
import java.util.List;
public final class AccessIncidentHandler {
    public record View(Incident incident, IncidentDetails details, List<IncidentEvent> events, long totalEvents) {
        @Override
        public String toString() {
            return "IncidentView[restricted]";
        }
    }
    private final PermissionChecker auth;
    private final IncidentReader reader;
    private final IncidentAccess access;
    private final UnitOfWork tx;
    private final ChildrenAudit audit;
    private final Clock clock;
    private final ZoneId zone;
    public AccessIncidentHandler(PermissionChecker auth, IncidentReader reader, IncidentAccess access, UnitOfWork tx,
        ChildrenAudit audit, Clock clock, ZoneId zone) {
        this.auth = auth;
        this.reader = reader;
        this.access = access;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
        this.zone = zone;
    }
    public View handle(AccessIncidentCommand c) {
        auth.requirePermission(c.actorId(), "children.incidents.manage");
        ChildrenRules.page(c.limit(), c.offset());
        return tx.execute(() -> {
            Incident incident = access.current(c.incidentId(), clock.instant().atZone(zone).toLocalDate());
            IncidentDetails details =
                reader.details(incident.id()).orElseThrow(() -> ChildrenException.missing("Current incident details"));
            List<IncidentEvent> events = reader.events(incident.id(), c.limit(), c.offset());
            long total = reader.eventCount(incident.id());
            audit.record(c.actorId(), "incident.accessed", incident.id(), "", clock.instant());
            return new View(incident, details, events, total);
        });
    }
}

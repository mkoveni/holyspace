package org.onechurch.childrenministry.incidents.application.command.purgeexpiredincidents;
import org.onechurch.childrenministry.incidents.domain.repository.IncidentRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.ZoneId;
public final class PurgeExpiredIncidentsHandler {
    private final PermissionChecker auth;
    private final IncidentRepository repository;
    private final UnitOfWork tx;
    private final ChildrenAudit audit;
    private final Clock clock;
    private final ZoneId zone;
    public PurgeExpiredIncidentsHandler(PermissionChecker auth, IncidentRepository repository, UnitOfWork tx,
        ChildrenAudit audit, Clock clock, ZoneId zone) {
        this.auth = auth;
        this.repository = repository;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
        this.zone = zone;
    }
    public int handle(PurgeExpiredIncidentsCommand c) {
        auth.requirePermission(c.actorId(), "children.incidents.manage");
        ChildrenRules.page(c.limit(), 0);
        return tx.execute(() -> {
            int count = repository.purgeExpired(clock.instant().atZone(zone).toLocalDate(), c.limit());
            audit.record(c.actorId(), "incident.details.purged", c.actorId(), Integer.toString(count), clock.instant());
            return count;
        });
    }
}

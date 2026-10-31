package org.onechurch.events.application.command.createeventseries;
import org.onechurch.events.application.EventPlanning;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;
public final class CreateEventSeriesHandler {
    private final PermissionChecker auth;
    private final EventPlanning planning;
    public CreateEventSeriesHandler(PermissionChecker auth, EventPlanning planning) {
        this.auth = auth;
        this.planning = planning;
    }
    public UUID handle(CreateEventSeriesCommand c) {
        auth.requirePermission(c.actorId(), "events.manage");
        return planning.addSeries(c.actorId(), c.requestId(), c.eventId(), c.expectedVersion(), c.schedule(),
            c.previewHash());
    }
}

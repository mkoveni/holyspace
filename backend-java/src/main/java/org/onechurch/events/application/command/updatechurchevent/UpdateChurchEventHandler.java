package org.onechurch.events.application.command.updatechurchevent;
import org.onechurch.events.application.EventPlanning;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class UpdateChurchEventHandler {
    private final PermissionChecker auth;
    private final EventPlanning planning;
    public UpdateChurchEventHandler(PermissionChecker auth, EventPlanning planning) {
        this.auth = auth;
        this.planning = planning;
    }
    public void handle(UpdateChurchEventCommand c) {
        auth.requirePermission(c.actorId(), "events.manage");
        planning.update(c.actorId(), c.eventId(), c.expectedVersion(), c.title(), c.type(), c.description(), c.active(),
            c.reason());
    }
}

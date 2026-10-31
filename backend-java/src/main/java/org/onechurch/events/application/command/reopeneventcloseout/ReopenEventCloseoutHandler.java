package org.onechurch.events.application.command.reopeneventcloseout;
import org.onechurch.events.application.EventCloseouts;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ReopenEventCloseoutHandler {
    private final PermissionChecker auth;
    private final EventCloseouts service;
    public ReopenEventCloseoutHandler(PermissionChecker auth, EventCloseouts service) {
        this.auth = auth;
        this.service = service;
    }
    public void handle(ReopenEventCloseoutCommand c) {
        auth.requirePermission(c.actorId(), "events.manage");
        service.reopen(c.actorId(), c.occurrenceId(), c.expectedVersion(), c.reason());
    }
}

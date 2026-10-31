package org.onechurch.events.application.command.closeeventoccurrence;
import org.onechurch.events.application.EventCloseouts;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class CloseEventOccurrenceHandler {
    private final PermissionChecker auth;
    private final EventCloseouts service;
    public CloseEventOccurrenceHandler(PermissionChecker auth, EventCloseouts service) {
        this.auth = auth;
        this.service = service;
    }
    public void handle(CloseEventOccurrenceCommand c) {
        auth.requirePermission(c.actorId(), "events.manage");
        service.close(c.actorId(), c.occurrenceId(), c.expectedVersion(), c.reviewHash(), c.note(), c.followUp(),
            c.attendanceReviewed(), c.followUpReviewed());
    }
}

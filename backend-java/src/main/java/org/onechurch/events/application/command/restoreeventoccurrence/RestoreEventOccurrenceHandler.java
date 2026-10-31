package org.onechurch.events.application.command.restoreeventoccurrence;
import org.onechurch.events.application.OccurrenceChanges;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class RestoreEventOccurrenceHandler {
    private final PermissionChecker auth;
    private final OccurrenceChanges changes;
    public RestoreEventOccurrenceHandler(PermissionChecker auth, OccurrenceChanges changes) {
        this.auth = auth;
        this.changes = changes;
    }
    public void handle(RestoreEventOccurrenceCommand c) {
        auth.requirePermission(c.actorId(), "events.manage");
        changes.apply(c.actorId(), "RESTORE", c.draft(), c.reviewHash(), c.reason());
    }
}

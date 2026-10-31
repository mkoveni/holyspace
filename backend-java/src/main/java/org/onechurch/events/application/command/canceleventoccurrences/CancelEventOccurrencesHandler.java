package org.onechurch.events.application.command.canceleventoccurrences;
import org.onechurch.events.application.OccurrenceChanges;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class CancelEventOccurrencesHandler {
    private final PermissionChecker auth;
    private final OccurrenceChanges changes;
    public CancelEventOccurrencesHandler(PermissionChecker auth, OccurrenceChanges changes) {
        this.auth = auth;
        this.changes = changes;
    }
    public void handle(CancelEventOccurrencesCommand c) {
        auth.requirePermission(c.actorId(), "events.manage");
        changes.apply(c.actorId(), "CANCEL", c.draft(), c.reviewHash(), c.reason());
    }
}

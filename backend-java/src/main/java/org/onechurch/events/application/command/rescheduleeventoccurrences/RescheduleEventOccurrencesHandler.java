package org.onechurch.events.application.command.rescheduleeventoccurrences;
import org.onechurch.events.application.OccurrenceChanges;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class RescheduleEventOccurrencesHandler {
    private final PermissionChecker auth;
    private final OccurrenceChanges changes;
    public RescheduleEventOccurrencesHandler(PermissionChecker auth, OccurrenceChanges changes) {
        this.auth = auth;
        this.changes = changes;
    }
    public void handle(RescheduleEventOccurrencesCommand c) {
        auth.requirePermission(c.actorId(), "events.manage");
        changes.apply(c.actorId(), "RESCHEDULE", c.draft(), c.reviewHash(), c.reason());
    }
}

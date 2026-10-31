package org.onechurch.events.application.query.geteventoccurrence;
import org.onechurch.events.application.EventViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetEventOccurrenceHandler {
    private final PermissionChecker auth;
    private final EventViews views;
    public GetEventOccurrenceHandler(PermissionChecker auth, EventViews views) {
        this.auth = auth;
        this.views = views;
    }
    public EventViews.Occurrence handle(GetEventOccurrenceQuery q) {
        auth.requirePermission("events.read");
        return views.occurrence(q.occurrenceId());
    }
}

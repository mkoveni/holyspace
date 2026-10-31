package org.onechurch.events.application.query.listchurchevents;
import org.onechurch.events.application.EventViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ListChurchEventsHandler {
    private final PermissionChecker auth;
    private final EventViews views;
    public ListChurchEventsHandler(PermissionChecker auth, EventViews views) {
        this.auth = auth;
        this.views = views;
    }
    public EventViews.Events handle(ListChurchEventsQuery q) {
        auth.requirePermission("events.read");
        return views.events(q.search(), q.includeArchived(), q.limit(), q.offset());
    }
}

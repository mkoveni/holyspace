package org.onechurch.events.application.query.listeventvenues;
import org.onechurch.events.application.EventViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ListEventVenuesHandler {
    private final PermissionChecker auth;
    private final EventViews views;
    public ListEventVenuesHandler(PermissionChecker auth, EventViews views) {
        this.auth = auth;
        this.views = views;
    }
    public EventViews.Venues handle(ListEventVenuesQuery q) {
        auth.requirePermission("events.read");
        return views.venues(q.search(), q.includeArchived(), q.limit(), q.offset());
    }
}

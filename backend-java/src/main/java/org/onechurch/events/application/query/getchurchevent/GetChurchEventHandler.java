package org.onechurch.events.application.query.getchurchevent;
import org.onechurch.events.application.EventViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetChurchEventHandler {
    private final PermissionChecker auth;
    private final EventViews views;
    public GetChurchEventHandler(PermissionChecker auth, EventViews views) {
        this.auth = auth;
        this.views = views;
    }
    public EventViews.Details handle(GetChurchEventQuery q) {
        auth.requirePermission("events.read");
        return views.event(q.eventId(), q.limit(), q.offset());
    }
}

package org.onechurch.events.application.query.listeventcalendar;
import org.onechurch.events.application.EventViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ListEventCalendarHandler {
    private final PermissionChecker auth;
    private final EventViews views;
    public ListEventCalendarHandler(PermissionChecker auth, EventViews views) {
        this.auth = auth;
        this.views = views;
    }
    public EventViews.Calendar handle(ListEventCalendarQuery q) {
        auth.requirePermission("events.read");
        return views.calendar(q.eventId(), q.search(), q.from(), q.until(), q.status(), q.limit(), q.offset());
    }
}

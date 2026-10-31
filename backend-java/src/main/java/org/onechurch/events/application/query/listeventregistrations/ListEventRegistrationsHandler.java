package org.onechurch.events.application.query.listeventregistrations;
import org.onechurch.events.application.EventViews;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ListEventRegistrationsHandler {
    private final PermissionChecker auth;
    private final EventViews views;
    public ListEventRegistrationsHandler(PermissionChecker auth, EventViews views) {
        this.auth = auth;
        this.views = views;
    }
    public EventViews.Registrations handle(ListEventRegistrationsQuery q) {
        auth.requirePermission("events.registrations.read");
        return views.registrations(q.occurrenceId(), q.status(), q.limit(), q.offset());
    }
}

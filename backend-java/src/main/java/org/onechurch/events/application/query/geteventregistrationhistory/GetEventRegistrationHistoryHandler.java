package org.onechurch.events.application.query.geteventregistrationhistory;
import org.onechurch.events.application.EventViews;
import org.onechurch.events.application.contract.EventJournalReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;
public final class GetEventRegistrationHistoryHandler {
    private final PermissionChecker auth;
    private final EventViews views;
    public GetEventRegistrationHistoryHandler(PermissionChecker auth, EventViews views) {
        this.auth = auth;
        this.views = views;
    }
    public List<EventJournalReader.RegistrationEntry> handle(GetEventRegistrationHistoryQuery q) {
        auth.requirePermission("events.registrations.read");
        return views.registrationHistory(q.registrationId(), q.limit(), q.offset());
    }
}

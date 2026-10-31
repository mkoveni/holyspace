package org.onechurch.events.application.query.geteventoccurrencehistory;
import org.onechurch.events.application.EventViews;
import org.onechurch.events.application.contract.EventJournalReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;
public final class GetEventOccurrenceHistoryHandler {
    private final PermissionChecker auth;
    private final EventViews views;
    public GetEventOccurrenceHistoryHandler(PermissionChecker auth, EventViews views) {
        this.auth = auth;
        this.views = views;
    }
    public List<EventJournalReader.OccurrenceEntry> handle(GetEventOccurrenceHistoryQuery q) {
        auth.requirePermission("events.read");
        return views.occurrenceHistory(q.occurrenceId(), q.limit(), q.offset());
    }
}

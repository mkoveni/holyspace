package org.onechurch.events.application.query.geteventcloseouthistory;
import org.onechurch.events.application.EventReadiness;
import org.onechurch.events.application.contract.CloseoutReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;
public final class GetEventCloseoutHistoryHandler {
    private final PermissionChecker auth;
    private final EventReadiness service;
    public GetEventCloseoutHistoryHandler(PermissionChecker auth, EventReadiness service) {
        this.auth = auth;
        this.service = service;
    }
    public List<CloseoutReader.History> handle(GetEventCloseoutHistoryQuery q) {
        auth.requirePermission("events.manage");
        return service.history(q.occurrenceId(), q.limit(), q.offset());
    }
}

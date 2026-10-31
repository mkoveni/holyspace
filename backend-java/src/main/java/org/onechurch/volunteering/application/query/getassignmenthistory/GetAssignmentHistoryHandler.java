package org.onechurch.volunteering.application.query.getassignmenthistory;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.ServingViews;
import org.onechurch.volunteering.application.contract.RosterReader;

import java.util.List;

public final class GetAssignmentHistoryHandler {
    private final PermissionChecker auth;
    private final RosterReader roster;

    public GetAssignmentHistoryHandler(PermissionChecker auth, RosterReader roster) {
        this.auth = auth;
        this.roster = roster;
    }
    public List<RosterReader.Event> handle(GetAssignmentHistoryQuery q) {
        auth.requirePermission("volunteering.read");
        ServingViews.page(q.limit(), q.offset());
        return roster.events(q.id(), q.limit(), q.offset());
    }
}

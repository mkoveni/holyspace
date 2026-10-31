package org.onechurch.volunteering.application.command.bulkcreatevolunteershifts;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.RosterPlanning;

import java.util.List;
import java.util.UUID;

public final class BulkCreateVolunteerShiftsHandler {
    private final PermissionChecker auth;
    private final RosterPlanning roster;
    public BulkCreateVolunteerShiftsHandler(PermissionChecker auth, RosterPlanning roster) {
        this.auth = auth;
        this.roster = roster;
    }
    public List<UUID> handle(BulkCreateVolunteerShiftsCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        return roster.bulk(c.actorId(), c.drafts());
    }
}

package org.onechurch.volunteering.application.command.expirevolunteerinvitations;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.RosterPlanning;

public final class ExpireVolunteerInvitationsHandler {
    private final PermissionChecker auth;
    private final RosterPlanning roster;
    public ExpireVolunteerInvitationsHandler(PermissionChecker auth, RosterPlanning roster) {
        this.auth = auth;
        this.roster = roster;
    }
    public int handle(ExpireVolunteerInvitationsCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        return roster.expire(c.actorId(), c.limit());
    }
}

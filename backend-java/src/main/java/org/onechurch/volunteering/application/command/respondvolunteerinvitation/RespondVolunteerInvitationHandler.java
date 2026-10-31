package org.onechurch.volunteering.application.command.respondvolunteerinvitation;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.RosterPlanning;

public final class RespondVolunteerInvitationHandler {
    private final PermissionChecker auth;
    private final RosterPlanning roster;
    public RespondVolunteerInvitationHandler(PermissionChecker auth, RosterPlanning roster) {
        this.auth = auth;
        this.roster = roster;
    }
    public void handle(RespondVolunteerInvitationCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.serve");
        roster.respond(c.actorId(), c.actorId(), c.invitationId(), c.expectedVersion(), c.decision());
    }
}

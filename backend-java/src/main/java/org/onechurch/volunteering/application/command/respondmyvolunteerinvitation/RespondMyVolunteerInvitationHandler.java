package org.onechurch.volunteering.application.command.respondmyvolunteerinvitation;
import org.onechurch.volunteering.application.MemberVolunteerActions;
public final class RespondMyVolunteerInvitationHandler {
    private final MemberVolunteerActions actions;
    public RespondMyVolunteerInvitationHandler(MemberVolunteerActions actions) {
        this.actions = actions;
    }
    public void handle(RespondMyVolunteerInvitationCommand c) {
        actions.respond(c.invitationId(), c.expectedVersion(), c.decision());
    }
}

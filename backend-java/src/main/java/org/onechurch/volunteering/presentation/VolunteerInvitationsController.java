package org.onechurch.volunteering.presentation;

import org.onechurch.volunteering.application.ServingViews;
import org.onechurch.volunteering.application.command.expirevolunteerinvitations.ExpireVolunteerInvitationsCommand;
import org.onechurch.volunteering.application.command.expirevolunteerinvitations.ExpireVolunteerInvitationsHandler;
import org.onechurch.volunteering.application.command.invitevolunteer.InviteVolunteerCommand;
import org.onechurch.volunteering.application.command.invitevolunteer.InviteVolunteerHandler;
import org.onechurch.volunteering.application.command.respondvolunteerinvitation.RespondVolunteerInvitationCommand;
import org.onechurch.volunteering.application.command.respondvolunteerinvitation.RespondVolunteerInvitationHandler;
import org.onechurch.volunteering.application.query.getownvolunteerschedule.GetOwnVolunteerScheduleHandler;
import org.onechurch.volunteering.application.query.getownvolunteerschedule.GetOwnVolunteerScheduleQuery;
import org.onechurch.volunteering.presentation.api.VolunteerInvitationsApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;

@RestController
public class VolunteerInvitationsController implements VolunteerInvitationsApi {
    private final InviteVolunteerHandler inviteVolunteer;
    private final RespondVolunteerInvitationHandler respondVolunteerInvitation;
    private final ExpireVolunteerInvitationsHandler expireVolunteerInvitations;
    private final GetOwnVolunteerScheduleHandler getOwnVolunteerSchedule;
    public VolunteerInvitationsController(InviteVolunteerHandler inviteVolunteer,
        RespondVolunteerInvitationHandler respondVolunteerInvitation,
        ExpireVolunteerInvitationsHandler expireVolunteerInvitations,
        GetOwnVolunteerScheduleHandler getOwnVolunteerSchedule) {
        this.inviteVolunteer = inviteVolunteer;
        this.respondVolunteerInvitation = respondVolunteerInvitation;
        this.expireVolunteerInvitations = expireVolunteerInvitations;
        this.getOwnVolunteerSchedule = getOwnVolunteerSchedule;
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public Created invite(UUID id, Invite b, OAuth2AuthenticatedPrincipal p) {
        return new Created(inviteVolunteer
            .handle(new InviteVolunteerCommand(UUID.fromString(p.getName()), id, b.expectedVersion(), b.expiresAt())));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.serve')")
    public void respond(UUID id, Respond b, OAuth2AuthenticatedPrincipal p) {
        respondVolunteerInvitation.handle(
            new RespondVolunteerInvitationCommand(UUID.fromString(p.getName()), id, b.expectedVersion(), b.decision()));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public int expire(Expire b, OAuth2AuthenticatedPrincipal p) {
        return expireVolunteerInvitations
            .handle(new ExpireVolunteerInvitationsCommand(UUID.fromString(p.getName()), b.limit()));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.serve')")
    public List<ServingViews.InvitationView> own(int limit, int offset, OAuth2AuthenticatedPrincipal p) {
        return getOwnVolunteerSchedule
            .handle(new GetOwnVolunteerScheduleQuery(UUID.fromString(p.getName()), limit, offset));
    }
}

package org.onechurch.volunteering.presentation;
import org.onechurch.volunteering.application.MemberVolunteerViews;
import org.onechurch.volunteering.application.command.respondmyvolunteerinvitation.RespondMyVolunteerInvitationCommand;
import org.onechurch.volunteering.application.command.respondmyvolunteerinvitation.RespondMyVolunteerInvitationHandler;
import org.onechurch.volunteering.application.command.updatemyvolunteeravailability.UpdateMyVolunteerAvailabilityCommand;
import org.onechurch.volunteering.application.command.updatemyvolunteeravailability.UpdateMyVolunteerAvailabilityHandler;
import org.onechurch.volunteering.application.query.getmyvolunteering.GetMyVolunteeringHandler;
import org.onechurch.volunteering.application.query.getmyvolunteering.GetMyVolunteeringQuery;
import org.onechurch.volunteering.presentation.api.MemberVolunteeringApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.ModelAttribute;
import org.springframework.web.bind.annotation.RestController;

import jakarta.servlet.http.HttpServletResponse;

import java.util.UUID;
@RestController
@PreAuthorize("isAuthenticated()")
public class MemberVolunteeringController implements MemberVolunteeringApi {
    private final GetMyVolunteeringHandler query;
    private final RespondMyVolunteerInvitationHandler respond;
    private final UpdateMyVolunteerAvailabilityHandler availability;
    public MemberVolunteeringController(GetMyVolunteeringHandler q, RespondMyVolunteerInvitationHandler r,
        UpdateMyVolunteerAvailabilityHandler a) {
        query = q;
        respond = r;
        availability = a;
    }
    @ModelAttribute
    void privateResponses(HttpServletResponse response) {
        response.setHeader("Cache-Control", "no-store");
    }
    @Override
    public MemberVolunteerViews.Overview overview(int limit, int offset) {
        return query.handle(new GetMyVolunteeringQuery(limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.serve')")
    public void respond(UUID id, Response b) {
        respond.handle(new RespondMyVolunteerInvitationCommand(id, b.expectedVersion(), b.decision()));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.serve')")
    public void availability(Availability b) {
        availability.handle(
            new UpdateMyVolunteerAvailabilityCommand(b.volunteerId(), b.expectedVersion(), b.weekly(), b.exceptions()));
    }
}

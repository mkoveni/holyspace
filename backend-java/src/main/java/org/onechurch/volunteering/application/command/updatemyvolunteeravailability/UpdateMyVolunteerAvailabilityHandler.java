package org.onechurch.volunteering.application.command.updatemyvolunteeravailability;
import org.onechurch.volunteering.application.MemberVolunteerActions;
public final class UpdateMyVolunteerAvailabilityHandler {
    private final MemberVolunteerActions actions;
    public UpdateMyVolunteerAvailabilityHandler(MemberVolunteerActions actions) {
        this.actions = actions;
    }
    public void handle(UpdateMyVolunteerAvailabilityCommand c) {
        actions.availability(c.volunteerId(), c.expectedVersion(), c.weekly(), c.exceptions());
    }
}

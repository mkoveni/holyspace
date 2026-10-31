package org.onechurch.volunteering.application.command.setvolunteeravailability;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.VolunteerProfiles;

public final class SetVolunteerAvailabilityHandler {
    private final PermissionChecker auth;
    private final VolunteerProfiles profiles;
    public SetVolunteerAvailabilityHandler(PermissionChecker auth, VolunteerProfiles profiles) {
        this.auth = auth;
        this.profiles = profiles;
    }
    public void handle(SetVolunteerAvailabilityCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        profiles.availability(c.actorId(), c.volunteerId(), c.expectedVersion(), c.weekly(), c.exceptions());
    }
}

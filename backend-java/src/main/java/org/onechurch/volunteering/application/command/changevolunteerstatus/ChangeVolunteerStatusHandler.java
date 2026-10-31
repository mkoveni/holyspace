package org.onechurch.volunteering.application.command.changevolunteerstatus;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.VolunteerProfiles;

public final class ChangeVolunteerStatusHandler {
    private final PermissionChecker auth;
    private final VolunteerProfiles profiles;
    public ChangeVolunteerStatusHandler(PermissionChecker auth, VolunteerProfiles profiles) {
        this.auth = auth;
        this.profiles = profiles;
    }
    public void handle(ChangeVolunteerStatusCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        profiles.status(c.actorId(), c.id(), c.expectedVersion(), c.status());
    }
}

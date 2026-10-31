package org.onechurch.volunteering.application.command.registervolunteer;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.VolunteerProfiles;

import java.util.UUID;

public final class RegisterVolunteerHandler {
    private final PermissionChecker auth;
    private final VolunteerProfiles profiles;
    public RegisterVolunteerHandler(PermissionChecker auth, VolunteerProfiles profiles) {
        this.auth = auth;
        this.profiles = profiles;
    }
    public UUID handle(RegisterVolunteerCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        if (c.accountId() != null) {
            auth.requirePermission("identity.manage");
        }
        return profiles.register(c.actorId(), c.personId(), c.accountId());
    }
}

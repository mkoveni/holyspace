package org.onechurch.volunteering.application.command.revokevolunteercredential;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.VolunteerProfiles;

public final class RevokeVolunteerCredentialHandler {
    private final PermissionChecker auth;
    private final VolunteerProfiles profiles;
    public RevokeVolunteerCredentialHandler(PermissionChecker auth, VolunteerProfiles profiles) {
        this.auth = auth;
        this.profiles = profiles;
    }
    public void handle(RevokeVolunteerCredentialCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.credentials.manage");
        profiles.revoke(c.actorId(), c.volunteerId(), c.expectedVersion(), c.credentialId(), c.reason());
    }
}

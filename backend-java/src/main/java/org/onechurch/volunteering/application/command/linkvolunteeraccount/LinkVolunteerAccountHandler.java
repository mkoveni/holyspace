package org.onechurch.volunteering.application.command.linkvolunteeraccount;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.VolunteerProfiles;

public final class LinkVolunteerAccountHandler {
    private final PermissionChecker auth;
    private final VolunteerProfiles profiles;
    public LinkVolunteerAccountHandler(PermissionChecker auth, VolunteerProfiles profiles) {
        this.auth = auth;
        this.profiles = profiles;
    }
    public void handle(LinkVolunteerAccountCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.manage");
        auth.requirePermission(c.actorId(), "identity.manage");
        profiles.account(c.actorId(), c.volunteerId(), c.expectedVersion(), c.accountId());
    }
}

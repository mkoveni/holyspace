package org.onechurch.volunteering.application.command.createcredentialtype;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.VolunteerProfiles;

import java.util.UUID;

public final class CreateCredentialTypeHandler {
    private final PermissionChecker auth;
    private final VolunteerProfiles profiles;
    public CreateCredentialTypeHandler(PermissionChecker auth, VolunteerProfiles profiles) {
        this.auth = auth;
        this.profiles = profiles;
    }
    public UUID handle(CreateCredentialTypeCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.credentials.manage");
        return profiles.type(c.actorId(), c.code(), c.name());
    }
}

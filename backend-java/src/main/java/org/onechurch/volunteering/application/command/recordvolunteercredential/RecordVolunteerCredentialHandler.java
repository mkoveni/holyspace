package org.onechurch.volunteering.application.command.recordvolunteercredential;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.VolunteerProfiles;

import java.util.UUID;

public final class RecordVolunteerCredentialHandler {
    private final PermissionChecker auth;
    private final VolunteerProfiles profiles;
    public RecordVolunteerCredentialHandler(PermissionChecker auth, VolunteerProfiles profiles) {
        this.auth = auth;
        this.profiles = profiles;
    }
    public UUID handle(RecordVolunteerCredentialCommand c) {
        auth.requirePermission(c.actorId(), "volunteering.credentials.manage");
        return profiles.credential(c.actorId(), c.volunteerId(), c.expectedVersion(), c.typeId(), c.validFrom(),
            c.validUntil(), c.evidence());
    }
}

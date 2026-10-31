package org.onechurch.volunteering.application.query.listcredentialtypes;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.CredentialType;

import java.util.List;

public final class ListCredentialTypesHandler {
    private final PermissionChecker auth;
    private final VolunteerReader volunteers;

    public ListCredentialTypesHandler(PermissionChecker auth, VolunteerReader volunteers) {
        this.auth = auth;
        this.volunteers = volunteers;
    }
    public List<CredentialType> handle(ListCredentialTypesQuery q) {
        auth.requirePermission("volunteering.read");
        return volunteers.credentialTypes();
    }
}

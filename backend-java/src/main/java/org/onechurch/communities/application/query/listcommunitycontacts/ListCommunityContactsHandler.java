package org.onechurch.communities.application.query.listcommunitycontacts;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityMessaging;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonEmailDirectory;

import java.util.List;

public final class ListCommunityContactsHandler {
    private final CommunityMessaging service;
    private final CommunityAccess access;
    private final PermissionChecker auth;
    public ListCommunityContactsHandler(CommunityMessaging service, CommunityAccess access, PermissionChecker auth) {
        this.service = service;
        this.access = access;
        this.auth = auth;
    }
    public List<PersonEmailDirectory.Email> handle(ListCommunityContactsQuery q) {
        auth.requirePermission("ministries.communications.send");
        access.directoryScope();
        return service.contacts(q.membershipId());
    }
}

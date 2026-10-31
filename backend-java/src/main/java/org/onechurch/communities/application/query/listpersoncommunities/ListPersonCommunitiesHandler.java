package org.onechurch.communities.application.query.listpersoncommunities;
import org.onechurch.communities.application.contract.CommunityParticipation;
import org.onechurch.communities.domain.CommunityRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;
public final class ListPersonCommunitiesHandler {
    private final CommunityParticipation reader;
    private final PermissionChecker auth;
    public ListPersonCommunitiesHandler(CommunityParticipation reader, PermissionChecker auth) {
        this.reader = reader;
        this.auth = auth;
    }
    public List<CommunityParticipation.Entry> handle(ListPersonCommunitiesQuery q) {
        auth.requirePermission("people.read");
        auth.requirePermission("ministries.read");
        CommunityRules.page(q.limit(), q.offset());
        return reader.forPerson(q.personId(), q.limit(), q.offset());
    }
}

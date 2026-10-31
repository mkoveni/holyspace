package org.onechurch.communities.application.query.getmycommunity;
import org.onechurch.communities.application.MemberCommunityViews;
import org.onechurch.communities.domain.CommunityException;
import org.onechurch.identityaccess.application.contract.MemberIdentity;

import java.time.Clock;
import java.util.UUID;
public final class GetMyCommunityHandler {
    private final MemberIdentity identity;
    private final MemberCommunityViews views;
    private final Clock clock;
    public GetMyCommunityHandler(MemberIdentity identity, MemberCommunityViews views, Clock clock) {
        this.identity = identity;
        this.views = views;
        this.clock = clock;
    }
    public MemberCommunityViews.MemberCommunityDetails handle(GetMyCommunityQuery q) {
        UUID person = identity.currentPerson().orElseThrow(CommunityException::missing);
        return views.details(person, q.communityId(), clock.instant(), q.leaderLimit(), q.leaderOffset());
    }
}

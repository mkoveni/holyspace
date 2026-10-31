package org.onechurch.communities.application.query.listmycommunities;
import org.onechurch.communities.application.MemberCommunityViews;
import org.onechurch.communities.application.contract.MemberCommunityReader;
import org.onechurch.communities.domain.CommunityException;
import org.onechurch.communities.domain.CommunityRules;
import org.onechurch.identityaccess.application.contract.MemberIdentity;

import java.time.Clock;
import java.util.List;
public final class ListMyCommunitiesHandler {
    private final MemberIdentity identity;
    private final MemberCommunityReader reader;
    private final Clock clock;
    public ListMyCommunitiesHandler(MemberIdentity identity, MemberCommunityReader reader, Clock clock) {
        this.identity = identity;
        this.reader = reader;
        this.clock = clock;
    }
    public MemberCommunityViews.MemberCommunitiesPage handle(ListMyCommunitiesQuery q) {
        CommunityRules.page(q.limit(), q.offset());
        if (q.kind() != null && !List.of("GROUP", "MINISTRY").contains(q.kind())) {
            throw CommunityException.invalid("Choose ministry or group.");
        }
        return identity.currentPerson().map(person -> {
            List<MemberCommunityReader.MemberCommunity> rows =
                reader.communities(person, q.kind(), clock.instant(), q.limit() + 1, q.offset());
            return new MemberCommunityViews.MemberCommunitiesPage(true, rows.stream().limit(q.limit()).toList(),
                rows.size() > q.limit());
        }).orElseGet(() -> new MemberCommunityViews.MemberCommunitiesPage(false, List.of(), false));
    }
}

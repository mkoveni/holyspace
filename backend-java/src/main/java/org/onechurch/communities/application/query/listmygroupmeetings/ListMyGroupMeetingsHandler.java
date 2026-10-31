package org.onechurch.communities.application.query.listmygroupmeetings;
import org.onechurch.communities.application.MemberCommunityViews;
import org.onechurch.communities.application.contract.MemberCommunityReader;
import org.onechurch.communities.domain.CommunityException;
import org.onechurch.communities.domain.CommunityRules;
import org.onechurch.identityaccess.application.contract.MemberIdentity;

import java.time.Clock;
import java.time.Instant;
import java.util.List;
public final class ListMyGroupMeetingsHandler {
    private final MemberIdentity identity;
    private final MemberCommunityReader reader;
    private final MemberCommunityViews views;
    private final Clock clock;
    public ListMyGroupMeetingsHandler(MemberIdentity identity, MemberCommunityReader reader, MemberCommunityViews views,
        Clock clock) {
        this.identity = identity;
        this.reader = reader;
        this.views = views;
        this.clock = clock;
    }
    public MemberCommunityViews.MemberGroupMeetingsPage handle(ListMyGroupMeetingsQuery q) {
        CommunityRules.page(q.limit(), q.offset());
        return identity.currentPerson().map(person -> {
            Instant now = clock.instant();
            if (q.communityId() != null) {
                reader.community(person, q.communityId(), now).orElseThrow(CommunityException::missing);
            }
            List<MemberCommunityReader.Meeting> rows =
                reader.meetings(person, q.communityId(), now, now.plusSeconds(180L * 86400), q.limit() + 1, q.offset());
            return new MemberCommunityViews.MemberGroupMeetingsPage(true,
                views.resolve(rows.stream().limit(q.limit()).toList()), rows.size() > q.limit());
        }).orElseGet(() -> new MemberCommunityViews.MemberGroupMeetingsPage(false, List.of(), false));
    }
}

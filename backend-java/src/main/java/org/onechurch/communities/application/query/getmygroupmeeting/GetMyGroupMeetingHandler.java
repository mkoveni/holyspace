package org.onechurch.communities.application.query.getmygroupmeeting;
import org.onechurch.communities.application.MemberCommunityViews;
import org.onechurch.communities.application.contract.MemberCommunityReader;
import org.onechurch.communities.domain.CommunityException;
import org.onechurch.identityaccess.application.contract.MemberIdentity;

import java.time.Clock;
import java.util.List;
import java.util.UUID;
public final class GetMyGroupMeetingHandler {
    private final MemberIdentity identity;
    private final MemberCommunityReader reader;
    private final MemberCommunityViews views;
    private final Clock clock;
    public GetMyGroupMeetingHandler(MemberIdentity identity, MemberCommunityReader reader, MemberCommunityViews views,
        Clock clock) {
        this.identity = identity;
        this.reader = reader;
        this.views = views;
        this.clock = clock;
    }
    public MemberCommunityViews.MemberGroupMeeting handle(GetMyGroupMeetingQuery q) {
        UUID person = identity.currentPerson().orElseThrow(CommunityException::missing);
        MemberCommunityReader.Meeting row =
            reader.meeting(person, q.meetingId(), clock.instant()).orElseThrow(CommunityException::missing);
        return views.resolve(List.of(row)).getFirst();
    }
}

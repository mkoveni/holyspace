package org.onechurch.communities.infrastructure;
import org.onechurch.communities.application.MemberCommunityViews;
import org.onechurch.communities.application.contract.MemberCommunityReader;
import org.onechurch.communities.application.query.getmycommunity.GetMyCommunityHandler;
import org.onechurch.communities.application.query.getmygroupmeeting.GetMyGroupMeetingHandler;
import org.onechurch.communities.application.query.listmycommunities.ListMyCommunitiesHandler;
import org.onechurch.communities.application.query.listmygroupmeetings.ListMyGroupMeetingsHandler;
import org.onechurch.events.application.contract.CommunityScheduleReader;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.people.application.contract.PersonDirectory;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
@Configuration
public class MemberCommunitiesConfiguration {
    @Bean
    MemberCommunityViews memberCommunityViews(MemberCommunityReader reader, PersonDirectory people,
        CommunityScheduleReader calendar) {
        return new MemberCommunityViews(reader, people, calendar);
    }
    @Bean
    ListMyCommunitiesHandler listMyCommunities(MemberIdentity identity, MemberCommunityReader reader,
        MemberCommunityViews views, Clock clock) {
        return new ListMyCommunitiesHandler(identity, reader, clock);
    }
    @Bean
    GetMyCommunityHandler getMyCommunity(MemberIdentity identity, MemberCommunityReader reader,
        MemberCommunityViews views, Clock clock) {
        return new GetMyCommunityHandler(identity, views, clock);
    }
    @Bean
    ListMyGroupMeetingsHandler listMyGroupMeetings(MemberIdentity identity, MemberCommunityReader reader,
        MemberCommunityViews views, Clock clock) {
        return new ListMyGroupMeetingsHandler(identity, reader, views, clock);
    }
    @Bean
    GetMyGroupMeetingHandler getMyGroupMeeting(MemberIdentity identity, MemberCommunityReader reader,
        MemberCommunityViews views, Clock clock) {
        return new GetMyGroupMeetingHandler(identity, reader, views, clock);
    }
}

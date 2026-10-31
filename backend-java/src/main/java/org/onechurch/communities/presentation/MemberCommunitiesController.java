package org.onechurch.communities.presentation;
import org.onechurch.communities.application.MemberCommunityViews;
import org.onechurch.communities.application.query.getmycommunity.GetMyCommunityHandler;
import org.onechurch.communities.application.query.getmycommunity.GetMyCommunityQuery;
import org.onechurch.communities.application.query.getmygroupmeeting.GetMyGroupMeetingHandler;
import org.onechurch.communities.application.query.getmygroupmeeting.GetMyGroupMeetingQuery;
import org.onechurch.communities.application.query.listmycommunities.ListMyCommunitiesHandler;
import org.onechurch.communities.application.query.listmycommunities.ListMyCommunitiesQuery;
import org.onechurch.communities.application.query.listmygroupmeetings.ListMyGroupMeetingsHandler;
import org.onechurch.communities.application.query.listmygroupmeetings.ListMyGroupMeetingsQuery;
import org.onechurch.communities.presentation.api.MemberCommunitiesApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.ModelAttribute;
import org.springframework.web.bind.annotation.RestController;

import jakarta.servlet.http.HttpServletResponse;

import java.util.UUID;
@RestController
@PreAuthorize("isAuthenticated()")
public class MemberCommunitiesController implements MemberCommunitiesApi {
    private final ListMyCommunitiesHandler list;
    private final GetMyCommunityHandler detail;
    private final ListMyGroupMeetingsHandler meetings;
    private final GetMyGroupMeetingHandler meeting;
    public MemberCommunitiesController(ListMyCommunitiesHandler list, GetMyCommunityHandler detail,
        ListMyGroupMeetingsHandler meetings, GetMyGroupMeetingHandler meeting) {
        this.list = list;
        this.detail = detail;
        this.meetings = meetings;
        this.meeting = meeting;
    }
    @ModelAttribute
    void privateResponses(HttpServletResponse response) {
        response.setHeader("Cache-Control", "no-store");
    }
    @Override
    public MemberCommunityViews.MemberCommunitiesPage list(String kind, int limit, int offset) {
        return list.handle(new ListMyCommunitiesQuery(kind, limit, offset));
    }
    @Override
    public MemberCommunityViews.MemberCommunityDetails detail(UUID id, int leaderLimit, int leaderOffset) {
        return detail.handle(new GetMyCommunityQuery(id, leaderLimit, leaderOffset));
    }
    @Override
    public MemberCommunityViews.MemberGroupMeetingsPage meetings(UUID communityId, int limit, int offset) {
        return meetings.handle(new ListMyGroupMeetingsQuery(communityId, limit, offset));
    }
    @Override
    public MemberCommunityViews.MemberGroupMeeting meeting(UUID id) {
        return meeting.handle(new GetMyGroupMeetingQuery(id));
    }
}

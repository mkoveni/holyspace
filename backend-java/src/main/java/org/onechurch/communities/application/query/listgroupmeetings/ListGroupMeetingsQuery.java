package org.onechurch.communities.application.query.listgroupmeetings;
import java.util.UUID;
public record ListGroupMeetingsQuery(UUID communityId, int limit, int offset) {}

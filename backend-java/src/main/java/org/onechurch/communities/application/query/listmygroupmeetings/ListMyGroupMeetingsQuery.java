package org.onechurch.communities.application.query.listmygroupmeetings;
import java.util.UUID;
public record ListMyGroupMeetingsQuery(UUID communityId, int limit, int offset) {}

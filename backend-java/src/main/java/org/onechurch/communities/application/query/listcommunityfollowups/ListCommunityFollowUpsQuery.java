package org.onechurch.communities.application.query.listcommunityfollowups;
import java.util.UUID;
public record ListCommunityFollowUpsQuery(UUID communityId, boolean completed, int limit, int offset) {}

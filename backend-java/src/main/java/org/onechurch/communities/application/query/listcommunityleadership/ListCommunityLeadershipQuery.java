package org.onechurch.communities.application.query.listcommunityleadership;
import java.util.UUID;
public record ListCommunityLeadershipQuery(UUID communityId, int limit, int offset) {}

package org.onechurch.communities.application.query.listcommunitymembers;
import java.util.UUID;
public record ListCommunityMembersQuery(UUID communityId, boolean ended, int limit, int offset) {}

package org.onechurch.communities.application.query.getmycommunity;
import java.util.UUID;
public record GetMyCommunityQuery(UUID communityId, int leaderLimit, int leaderOffset) {}

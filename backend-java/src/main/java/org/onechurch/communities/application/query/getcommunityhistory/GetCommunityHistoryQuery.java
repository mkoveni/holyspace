package org.onechurch.communities.application.query.getcommunityhistory;
import java.util.UUID;
public record GetCommunityHistoryQuery(UUID communityId, int limit, int offset) {}

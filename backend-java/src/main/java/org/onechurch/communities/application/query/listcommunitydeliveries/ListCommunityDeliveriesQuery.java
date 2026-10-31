package org.onechurch.communities.application.query.listcommunitydeliveries;
import java.util.UUID;
public record ListCommunityDeliveriesQuery(UUID communityId, int limit, int offset) {}

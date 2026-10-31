package org.onechurch.communities.application.query.listpersoncommunities;
import java.util.UUID;
public record ListPersonCommunitiesQuery(UUID personId, int limit, int offset) {}

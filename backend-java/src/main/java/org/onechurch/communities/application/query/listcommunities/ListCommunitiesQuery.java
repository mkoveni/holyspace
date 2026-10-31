package org.onechurch.communities.application.query.listcommunities;
public record ListCommunitiesQuery(String kind, String search, boolean archived, int limit, int offset) {}

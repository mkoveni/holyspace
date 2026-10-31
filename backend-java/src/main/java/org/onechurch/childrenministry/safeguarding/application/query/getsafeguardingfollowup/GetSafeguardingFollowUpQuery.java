package org.onechurch.childrenministry.safeguarding.application.query.getsafeguardingfollowup;
import java.util.UUID;
public record GetSafeguardingFollowUpQuery(UUID programmeId, String search, String kind, String state, int days,
    int limit, int offset) {}

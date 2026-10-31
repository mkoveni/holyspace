package org.onechurch.communities.application.query.getgrouproster;
import java.util.UUID;
public record GetGroupRosterQuery(UUID meetingId, int limit, int offset) {}

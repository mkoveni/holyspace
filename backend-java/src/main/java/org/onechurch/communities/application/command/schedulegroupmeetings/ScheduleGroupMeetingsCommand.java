package org.onechurch.communities.application.command.schedulegroupmeetings;
import org.onechurch.events.application.contract.CommunityCalendar;

import java.util.UUID;
public record ScheduleGroupMeetingsCommand(UUID actorId, UUID requestId, UUID communityId, long expectedVersion,
    CommunityCalendar.Schedule schedule, String reviewHash) {}

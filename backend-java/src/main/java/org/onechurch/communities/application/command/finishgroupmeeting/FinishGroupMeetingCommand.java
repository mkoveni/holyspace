package org.onechurch.communities.application.command.finishgroupmeeting;
import java.util.UUID;
public record FinishGroupMeetingCommand(UUID actorId, UUID meetingId, long expectedVersion, String status,
    String reason) {}

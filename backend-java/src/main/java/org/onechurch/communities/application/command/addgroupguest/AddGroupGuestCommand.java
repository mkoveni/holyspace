package org.onechurch.communities.application.command.addgroupguest;
import java.util.UUID;
public record AddGroupGuestCommand(UUID actorId, UUID meetingId, long expectedVersion, UUID personId, String reason) {}

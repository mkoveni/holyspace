package org.onechurch.communities.application.command.preparegrouproster;
import java.util.UUID;
public record PrepareGroupRosterCommand(UUID actorId, UUID meetingId, long expectedVersion) {}

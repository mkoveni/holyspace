package org.onechurch.communities.application.command.recordgroupattendance;
import java.util.UUID;
public record RecordGroupAttendanceCommand(UUID actorId, UUID rosterId, long expectedVersion, String status,
    String reason) {}

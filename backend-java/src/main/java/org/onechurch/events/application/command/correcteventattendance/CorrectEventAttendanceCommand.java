package org.onechurch.events.application.command.correcteventattendance;
import java.util.UUID;
public record CorrectEventAttendanceCommand(UUID actorId, UUID registrationId, long expectedVersion, String status,
    String reason) {}

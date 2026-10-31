package org.onechurch.events.application.command.scanmemberattendance;
import java.util.UUID;
public record ScanMemberAttendanceCommand(UUID actorId, UUID occurrenceId, UUID requestId, String credential) {
    @Override
    public String toString() {
        return "ScanMemberAttendanceCommand[credential=redacted]";
    }
}

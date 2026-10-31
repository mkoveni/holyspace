package org.onechurch.childrenministry.sessions.application.command.issuemychildpickuplabel;
import java.util.UUID;
public record IssueMyChildPickupLabelCommand(UUID childId, UUID attendanceId, long expectedVersion,
    boolean replacePrevious) {}

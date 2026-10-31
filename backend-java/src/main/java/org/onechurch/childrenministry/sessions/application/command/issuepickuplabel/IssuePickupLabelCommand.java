package org.onechurch.childrenministry.sessions.application.command.issuepickuplabel;

import java.util.UUID;
public record IssuePickupLabelCommand(UUID actorId, UUID attendanceId, long expectedVersion,
    String replacementReason) {}

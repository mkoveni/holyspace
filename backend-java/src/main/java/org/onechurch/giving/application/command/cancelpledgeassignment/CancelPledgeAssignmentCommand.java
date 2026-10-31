package org.onechurch.giving.application.command.cancelpledgeassignment;
import java.util.UUID;
public record CancelPledgeAssignmentCommand(UUID actorId, UUID assignmentId, long expectedVersion, String reason) {}

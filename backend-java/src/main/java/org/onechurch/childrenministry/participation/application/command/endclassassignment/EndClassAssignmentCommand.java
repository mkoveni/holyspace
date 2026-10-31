package org.onechurch.childrenministry.participation.application.command.endclassassignment;

import java.util.UUID;
public record EndClassAssignmentCommand(UUID actorId, UUID assignmentId, long expectedVersion) {}

package org.onechurch.people.application.command.endrelationship;

import java.util.UUID;

public record EndRelationshipCommand(UUID actorId, UUID relationshipId, long expectedVersion) {}

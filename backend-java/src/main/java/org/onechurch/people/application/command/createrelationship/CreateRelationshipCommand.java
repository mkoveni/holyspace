package org.onechurch.people.application.command.createrelationship;

import org.onechurch.people.domain.RelationshipType;

import java.util.UUID;

public record CreateRelationshipCommand(UUID actorId, UUID sourceId, UUID targetId, RelationshipType type) {}

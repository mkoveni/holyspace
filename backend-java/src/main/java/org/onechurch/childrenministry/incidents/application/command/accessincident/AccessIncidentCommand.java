package org.onechurch.childrenministry.incidents.application.command.accessincident;
import java.util.UUID;
public record AccessIncidentCommand(UUID actorId, UUID incidentId, int limit, int offset) {}

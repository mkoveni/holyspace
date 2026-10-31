package org.onechurch.childrenministry.participation.application.command.createlocation;

import java.util.UUID;
public record CreateLocationCommand(UUID actorId, String name, String timeZone) {}

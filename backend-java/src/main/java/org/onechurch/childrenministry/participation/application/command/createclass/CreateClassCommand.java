package org.onechurch.childrenministry.participation.application.command.createclass;

import java.util.UUID;
public record CreateClassCommand(UUID actorId, UUID programmeId, String name, int minAgeMonths, int maxAgeMonths) {}

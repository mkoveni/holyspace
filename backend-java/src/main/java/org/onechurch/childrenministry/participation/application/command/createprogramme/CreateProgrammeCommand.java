package org.onechurch.childrenministry.participation.application.command.createprogramme;

import java.util.UUID;
public record CreateProgrammeCommand(UUID actorId, String name, int minStaff, int childrenPerStaff) {}

package org.onechurch.childrenministry.participation.application.command.assignclass;

import java.time.LocalDate;
import java.util.UUID;
public record AssignClassCommand(UUID actorId, UUID enrolmentId, UUID classId, LocalDate validFrom,
    LocalDate validUntil) {}

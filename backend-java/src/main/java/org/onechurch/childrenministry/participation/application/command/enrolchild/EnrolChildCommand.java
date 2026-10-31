package org.onechurch.childrenministry.participation.application.command.enrolchild;

import java.time.LocalDate;
import java.util.UUID;
public record EnrolChildCommand(UUID actorId, UUID programmeId, UUID childId, LocalDate validFrom,
    LocalDate validUntil) {}

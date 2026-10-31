package org.onechurch.childrenministry.safeguarding.application.command.savechildsupport;

import java.time.LocalDate;
import java.util.UUID;
public record SaveChildSupportCommand(UUID actorId, UUID childId, long expectedVersion, String allergies,
    String careInstructions, String restrictedNotes, LocalDate reviewOn, LocalDate retainUntil) {}

package org.onechurch.people.application.command.addhouseholdmember;

import java.util.UUID;

public record AddHouseholdMemberCommand(UUID actorId, UUID householdId, long expectedVersion, UUID personId,
    boolean primary) {}

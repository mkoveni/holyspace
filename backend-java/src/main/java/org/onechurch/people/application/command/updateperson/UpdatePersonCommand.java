package org.onechurch.people.application.command.updateperson;

import org.onechurch.people.domain.MembershipType;
import org.onechurch.people.domain.PersonDetails;

import java.util.UUID;

public record UpdatePersonCommand(UUID actorId, UUID personId, long expectedVersion, PersonDetails details,
    MembershipType membershipType) {}

package org.onechurch.people.application.command.registerperson;

import org.onechurch.people.domain.MembershipType;
import org.onechurch.people.domain.PersonDetails;

import java.util.UUID;

public record RegisterPersonCommand(UUID actorId, PersonDetails details, MembershipType membershipType) {}

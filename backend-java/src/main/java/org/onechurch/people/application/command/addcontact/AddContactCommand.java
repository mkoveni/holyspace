package org.onechurch.people.application.command.addcontact;

import org.onechurch.people.domain.ContactMethod;

import java.util.UUID;

public record AddContactCommand(UUID actorId, UUID personId, long expectedVersion, ContactMethod.Kind kind,
    String value, boolean primary) {}

package org.onechurch.people.application.command.savemycontact;
import org.onechurch.people.domain.ContactMethod;

import java.util.UUID;
public record SaveMyContactCommand(UUID actorId, UUID contactId, ContactMethod.Kind kind, String value,
    long expectedVersion) {}

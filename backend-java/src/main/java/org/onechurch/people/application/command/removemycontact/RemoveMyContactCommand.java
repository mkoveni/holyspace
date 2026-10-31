package org.onechurch.people.application.command.removemycontact;
import java.util.UUID;
public record RemoveMyContactCommand(UUID actorId, UUID contactId, long expectedVersion) {}

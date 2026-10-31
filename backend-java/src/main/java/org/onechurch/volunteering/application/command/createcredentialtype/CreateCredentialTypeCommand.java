package org.onechurch.volunteering.application.command.createcredentialtype;

import java.util.UUID;

public record CreateCredentialTypeCommand(UUID actorId, String code, String name) {}

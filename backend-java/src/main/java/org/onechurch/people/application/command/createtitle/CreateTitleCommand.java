package org.onechurch.people.application.command.createtitle;

import java.util.UUID;

public record CreateTitleCommand(UUID actorId, String code, String label) {}

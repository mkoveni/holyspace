package org.onechurch.communications.application.command.updatetemplate;
import java.util.List;
import java.util.UUID;
public record UpdateTemplateCommand(UUID actorId, UUID id, long expectedVersion, String name, String subject,
    String html, List<String> variables) {}

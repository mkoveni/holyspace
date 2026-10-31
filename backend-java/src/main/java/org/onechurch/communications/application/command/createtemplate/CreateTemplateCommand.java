package org.onechurch.communications.application.command.createtemplate;
import java.util.List;
import java.util.UUID;
public record CreateTemplateCommand(UUID actorId, String key, String name, String subject, String html,
    List<String> variables) {}

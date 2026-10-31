package org.onechurch.communications.application.command.publishtemplate;
import java.util.UUID;
public record PublishTemplateCommand(UUID actorId, UUID id, long expectedVersion) {}

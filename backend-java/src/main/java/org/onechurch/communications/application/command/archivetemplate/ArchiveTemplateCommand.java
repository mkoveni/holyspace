package org.onechurch.communications.application.command.archivetemplate;
import java.util.UUID;
public record ArchiveTemplateCommand(UUID actorId, UUID id, long expectedVersion) {}

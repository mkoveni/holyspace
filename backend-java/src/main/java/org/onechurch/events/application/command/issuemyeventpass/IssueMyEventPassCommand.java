package org.onechurch.events.application.command.issuemyeventpass;
import java.util.UUID;
public record IssueMyEventPassCommand(UUID actorId, UUID occurrenceId) {}

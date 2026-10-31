package org.onechurch.giving.application.command.issueacknowledgement;
import java.util.UUID;
public record IssueAcknowledgementCommand(UUID actorId, UUID requestId, UUID giftId) {}

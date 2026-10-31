package org.onechurch.volunteering.application.command.revokeservingapproval;

import java.util.UUID;

public record RevokeServingApprovalCommand(UUID actorId, UUID teamId, long expectedVersion, UUID volunteerId,
    UUID recordId, boolean roleApproval) {}

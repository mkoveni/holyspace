package org.onechurch.giving.application.command.verifydonorlink;
import java.util.UUID;
public record VerifyDonorLinkCommand(UUID actorId, UUID accountId, UUID personId, boolean active, long expectedVersion,
    String evidence) {}

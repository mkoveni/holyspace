package org.onechurch.identityaccess.application.command.verifymemberlink;
import java.util.UUID;
public record VerifyMemberLinkCommand(UUID actorId, UUID accountId, UUID personId, boolean active, long expectedVersion,
    String evidence) {}

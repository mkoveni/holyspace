package org.onechurch.childrenministry.safeguarding.application.command.publishconsentpolicy;

import java.util.UUID;
public record PublishConsentPolicyCommand(UUID actorId, UUID programmeId, long expectedVersion, String label,
    String documentReference, String contentHash) {}

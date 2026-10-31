package org.onechurch.childrenministry.messaging.application.query.listguardiancommunications;
import java.util.UUID;
public record ListGuardianCommunicationsQuery(UUID childId, String status, int limit, int offset) {}

package org.onechurch.giving.application.query.listpledges;
import java.util.UUID;
public record ListPledgesQuery(UUID donorId, String status, int limit, int offset) {}

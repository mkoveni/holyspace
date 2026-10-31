package org.onechurch.giving.application.query.getpledge;
import java.util.UUID;
public record GetPledgeQuery(UUID pledgeId, int limit, int offset) {}

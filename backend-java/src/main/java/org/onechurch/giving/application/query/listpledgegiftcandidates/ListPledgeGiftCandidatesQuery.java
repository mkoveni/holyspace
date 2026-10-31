package org.onechurch.giving.application.query.listpledgegiftcandidates;
import java.util.UUID;
public record ListPledgeGiftCandidatesQuery(UUID pledgeId, int limit, int offset) {}

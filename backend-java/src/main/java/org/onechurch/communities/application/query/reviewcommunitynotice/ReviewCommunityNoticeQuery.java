package org.onechurch.communities.application.query.reviewcommunitynotice;
import java.util.UUID;
public record ReviewCommunityNoticeQuery(UUID membershipId, UUID contactId, String message) {}

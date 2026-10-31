package org.onechurch.events.application.query.revieweventnotification;
import org.onechurch.events.application.EventMessageReview;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.time.Clock;
public final class ReviewEventNotificationHandler {
    private final PermissionChecker auth;
    private final EventMessageReview review;
    private final Clock clock;
    public ReviewEventNotificationHandler(PermissionChecker auth, EventMessageReview review, Clock clock) {
        this.auth = auth;
        this.review = review;
        this.clock = clock;
    }
    public EventMessageReview.Review handle(ReviewEventNotificationQuery q) {
        auth.requirePermission("events.communications.send");
        return review.review(q.registrationId(), q.contactId(), q.purpose(), q.message(), clock.instant());
    }
}

package org.onechurch.volunteering.application.query.reviewvolunteerreminder;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.ReminderReview;

import java.time.Clock;

public final class ReviewVolunteerReminderHandler {
    private final PermissionChecker auth;
    private final ReminderReview review;
    private final Clock clock;
    public ReviewVolunteerReminderHandler(PermissionChecker auth, ReminderReview review, Clock clock) {
        this.auth = auth;
        this.review = review;
        this.clock = clock;
    }
    public ReminderReview.Review handle(ReviewVolunteerReminderQuery q) {
        auth.requirePermission("volunteering.communications.send");
        return review.review(q.assignmentId(), q.contactId(), q.message(), clock.instant());
    }
}

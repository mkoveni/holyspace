package org.onechurch.events.application.query.previeweventschedule;
import org.onechurch.events.application.ScheduleReview;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class PreviewEventScheduleHandler {
    private final PermissionChecker auth;
    private final ScheduleReview review;
    public PreviewEventScheduleHandler(PermissionChecker auth, ScheduleReview review) {
        this.auth = auth;
        this.review = review;
    }
    public ScheduleReview.Preview handle(PreviewEventScheduleQuery q) {
        auth.requirePermission("events.read");
        return review.preview(q.draft());
    }
}

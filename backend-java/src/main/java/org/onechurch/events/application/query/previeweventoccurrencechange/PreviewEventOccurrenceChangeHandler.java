package org.onechurch.events.application.query.previeweventoccurrencechange;
import org.onechurch.events.application.OccurrenceChangeReview;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class PreviewEventOccurrenceChangeHandler {
    private final PermissionChecker auth;
    private final OccurrenceChangeReview review;
    public PreviewEventOccurrenceChangeHandler(PermissionChecker auth, OccurrenceChangeReview review) {
        this.auth = auth;
        this.review = review;
    }
    public OccurrenceChangeReview.Preview handle(PreviewEventOccurrenceChangeQuery q) {
        auth.requirePermission("events.read");
        return review.preview(q.draft());
    }
}

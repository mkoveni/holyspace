package org.onechurch.volunteering.application.query.listremindercontacts;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.volunteering.application.ReminderReview;

import java.util.List;

public final class ListReminderContactsHandler {
    private final PermissionChecker auth;
    private final ReminderReview review;
    public ListReminderContactsHandler(PermissionChecker auth, ReminderReview review) {
        this.auth = auth;
        this.review = review;
    }
    public List<PersonEmailDirectory.Email> handle(ListReminderContactsQuery q) {
        auth.requirePermission("volunteering.communications.send");
        return review.contacts(q.assignmentId());
    }
}

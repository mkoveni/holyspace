package org.onechurch.events.application.query.listeventmessagecontacts;
import org.onechurch.events.application.EventMessageReview;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonEmailDirectory;

import java.util.List;
public final class ListEventMessageContactsHandler {
    private final PermissionChecker auth;
    private final EventMessageReview review;
    public ListEventMessageContactsHandler(PermissionChecker auth, EventMessageReview review) {
        this.auth = auth;
        this.review = review;
    }
    public List<PersonEmailDirectory.Email> handle(ListEventMessageContactsQuery q) {
        auth.requirePermission("events.communications.send");
        return review.contacts(q.registrationId());
    }
}

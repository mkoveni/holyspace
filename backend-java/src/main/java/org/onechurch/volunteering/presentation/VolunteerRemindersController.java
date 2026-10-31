package org.onechurch.volunteering.presentation;

import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.volunteering.application.ReminderReview;
import org.onechurch.volunteering.application.command.approvevolunteerreminder.ApproveVolunteerReminderCommand;
import org.onechurch.volunteering.application.command.approvevolunteerreminder.ApproveVolunteerReminderHandler;
import org.onechurch.volunteering.application.query.listremindercontacts.ListReminderContactsHandler;
import org.onechurch.volunteering.application.query.listremindercontacts.ListReminderContactsQuery;
import org.onechurch.volunteering.application.query.listvolunteerdeliveries.ListVolunteerDeliveriesHandler;
import org.onechurch.volunteering.application.query.listvolunteerdeliveries.ListVolunteerDeliveriesQuery;
import org.onechurch.volunteering.application.query.reviewvolunteerreminder.ReviewVolunteerReminderHandler;
import org.onechurch.volunteering.application.query.reviewvolunteerreminder.ReviewVolunteerReminderQuery;
import org.onechurch.volunteering.presentation.api.VolunteerRemindersApi;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;

@RestController
public class VolunteerRemindersController implements VolunteerRemindersApi {
    private final PermissionChecker auth;
    private final ApproveVolunteerReminderHandler approve;
    private final ReviewVolunteerReminderHandler review;
    private final ListReminderContactsHandler contacts;
    private final ListVolunteerDeliveriesHandler deliveries;
    public VolunteerRemindersController(PermissionChecker auth, ApproveVolunteerReminderHandler approve,
        ReviewVolunteerReminderHandler review, ListReminderContactsHandler contacts,
        ListVolunteerDeliveriesHandler deliveries) {
        this.auth = auth;
        this.approve = approve;
        this.review = review;
        this.contacts = contacts;
        this.deliveries = deliveries;
    }
    public List<PersonEmailDirectory.Email> contacts(UUID id) {
        auth.requirePermission("volunteering.communications.send");
        return contacts.handle(new ListReminderContactsQuery(id));
    }
    public ReminderReview.Review review(UUID id, Draft b) {
        auth.requirePermission("volunteering.communications.send");
        return review.handle(new ReviewVolunteerReminderQuery(id, b.contactId(), b.message()));
    }
    public UUID approve(UUID id, Approval b, OAuth2AuthenticatedPrincipal p) {
        auth.requirePermission("volunteering.communications.send");
        return approve.handle(new ApproveVolunteerReminderCommand(UUID.fromString(p.getName()), b.requestId(), id,
            b.contactId(), b.message(), b.reviewHash(), b.verificationEvidence()));
    }
    public DeliveryReader.Page deliveries(String status, int limit, int offset) {
        auth.requirePermission("volunteering.communications.send");
        return deliveries.handle(new ListVolunteerDeliveriesQuery(status, limit, offset));
    }
}

package org.onechurch.events.presentation;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.events.application.EventMessageReview;
import org.onechurch.events.application.command.approveeventnotification.ApproveEventNotificationCommand;
import org.onechurch.events.application.command.approveeventnotification.ApproveEventNotificationHandler;
import org.onechurch.events.application.query.listeventdeliveries.ListEventDeliveriesHandler;
import org.onechurch.events.application.query.listeventdeliveries.ListEventDeliveriesQuery;
import org.onechurch.events.application.query.listeventmessagecontacts.ListEventMessageContactsHandler;
import org.onechurch.events.application.query.listeventmessagecontacts.ListEventMessageContactsQuery;
import org.onechurch.events.application.query.revieweventnotification.ReviewEventNotificationHandler;
import org.onechurch.events.application.query.revieweventnotification.ReviewEventNotificationQuery;
import org.onechurch.events.presentation.api.EventNotificationsApi;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
public class EventNotificationsController implements EventNotificationsApi {
    private final ApproveEventNotificationHandler approveEventNotification;
    private final ListEventMessageContactsHandler listEventMessageContacts;
    private final ReviewEventNotificationHandler reviewEventNotification;
    private final ListEventDeliveriesHandler listEventDeliveries;
    public EventNotificationsController(ApproveEventNotificationHandler approveEventNotification,
        ListEventMessageContactsHandler listEventMessageContacts,
        ReviewEventNotificationHandler reviewEventNotification, ListEventDeliveriesHandler listEventDeliveries) {
        this.approveEventNotification = approveEventNotification;
        this.listEventMessageContacts = listEventMessageContacts;
        this.reviewEventNotification = reviewEventNotification;
        this.listEventDeliveries = listEventDeliveries;
    }
    @Override
    @PreAuthorize("hasAuthority('events.communications.send')")
    public Created approveEventNotification(UUID id, ApproveEventNotificationBody b, OAuth2AuthenticatedPrincipal p) {
        return new Created(
            approveEventNotification.handle(new ApproveEventNotificationCommand(UUID.fromString(p.getName()),
                b.requestId(), id, b.contactId(), b.purpose(), b.message(), b.reviewHash(), b.verificationEvidence())));
    }
    @Override
    @PreAuthorize("hasAuthority('events.communications.send')")
    public List<PersonEmailDirectory.Email> listEventMessageContacts(UUID id) {
        return listEventMessageContacts.handle(new ListEventMessageContactsQuery(id));
    }
    @Override
    @PreAuthorize("hasAuthority('events.communications.send')")
    public EventMessageReview.Review reviewEventNotification(UUID id, ReviewEventNotificationBody b) {
        return reviewEventNotification
            .handle(new ReviewEventNotificationQuery(id, b.contactId(), b.purpose(), b.message()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.communications.send')")
    public DeliveryReader.Page listEventDeliveries(String status, int limit, int offset) {
        return listEventDeliveries.handle(new ListEventDeliveriesQuery(status, limit, offset));
    }
}

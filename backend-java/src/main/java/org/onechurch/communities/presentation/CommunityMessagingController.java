package org.onechurch.communities.presentation;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communities.application.CommunityMessaging;
import org.onechurch.communities.application.command.approvecommunitynotice.ApproveCommunityNoticeCommand;
import org.onechurch.communities.application.command.approvecommunitynotice.ApproveCommunityNoticeHandler;
import org.onechurch.communities.application.query.listcommunitycontacts.ListCommunityContactsHandler;
import org.onechurch.communities.application.query.listcommunitycontacts.ListCommunityContactsQuery;
import org.onechurch.communities.application.query.listcommunitydeliveries.ListCommunityDeliveriesHandler;
import org.onechurch.communities.application.query.listcommunitydeliveries.ListCommunityDeliveriesQuery;
import org.onechurch.communities.application.query.reviewcommunitynotice.ReviewCommunityNoticeHandler;
import org.onechurch.communities.application.query.reviewcommunitynotice.ReviewCommunityNoticeQuery;
import org.onechurch.communities.presentation.api.CommunityMessagingApi;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
@PreAuthorize("hasAuthority('ministries.communications.send')")
public class CommunityMessagingController implements CommunityMessagingApi {
    private final ListCommunityDeliveriesHandler deliveries;

    private final ApproveCommunityNoticeHandler approveCommunityNoticeHandler;
    private final ListCommunityContactsHandler listCommunityContactsHandler;
    private final ReviewCommunityNoticeHandler reviewCommunityNoticeHandler;
    public CommunityMessagingController(ListCommunityDeliveriesHandler deliveries,
        ApproveCommunityNoticeHandler approveCommunityNoticeHandler,
        ListCommunityContactsHandler listCommunityContactsHandler,
        ReviewCommunityNoticeHandler reviewCommunityNoticeHandler) {
        this.deliveries = deliveries;
        this.approveCommunityNoticeHandler = approveCommunityNoticeHandler;
        this.listCommunityContactsHandler = listCommunityContactsHandler;
        this.reviewCommunityNoticeHandler = reviewCommunityNoticeHandler;
    }
    @Override
    public Created approveCommunityNotice(UUID membershipId, ApproveCommunityNoticeBody body,
        OAuth2AuthenticatedPrincipal actor) {
        return new Created(
            approveCommunityNoticeHandler.handle(new ApproveCommunityNoticeCommand(UUID.fromString(actor.getName()),
                body.requestId(), membershipId, body.contactId(), body.message(), body.reviewHash(), body.evidence())));
    }
    @Override
    public List<PersonEmailDirectory.Email> listCommunityContacts(UUID membershipId) {
        return listCommunityContactsHandler.handle(new ListCommunityContactsQuery(membershipId));
    }
    @Override
    public CommunityMessaging.Review reviewCommunityNotice(UUID membershipId, ReviewCommunityNoticeBody body) {
        return reviewCommunityNoticeHandler
            .handle(new ReviewCommunityNoticeQuery(membershipId, body.contactId(), body.message()));
    }
    @Override
    public List<DeliveryReader.Summary> deliveries(UUID communityId, int limit, int offset) {
        return deliveries.handle(new ListCommunityDeliveriesQuery(communityId, limit, offset));
    }
}

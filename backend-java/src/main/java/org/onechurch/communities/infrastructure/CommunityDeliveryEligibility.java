package org.onechurch.communities.infrastructure;
import org.onechurch.communications.application.contract.DeliveryEligibility;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityMessaging;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.communities.domain.Community;
import org.onechurch.communities.domain.Membership;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.identityaccess.application.contract.ResponsibleAccountDirectory;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.springframework.stereotype.Component;

import java.time.Instant;
import java.util.UUID;
@Component
public class CommunityDeliveryEligibility implements DeliveryEligibility {
    private final CommunityReader reader;
    private final PermissionChecker auth;
    private final ResponsibleAccountDirectory accounts;
    private final CommunityAccess access;
    private final PersonDirectory people;
    private final PersonEmailDirectory emails;
    public CommunityDeliveryEligibility(CommunityReader reader, PermissionChecker auth,
        ResponsibleAccountDirectory accounts, CommunityAccess access, PersonDirectory people,
        PersonEmailDirectory emails) {
        this.reader = reader;
        this.auth = auth;
        this.accounts = accounts;
        this.access = access;
        this.people = people;
        this.emails = emails;
    }
    public String sourceContext() {
        return "communities";
    }
    public void authorize(DeliverySnapshot d, UUID actor, String operation) {
        auth.requirePermission(actor, "ministries.communications.send");
        reader.notice(d.communicationId()).ifPresent(n -> access.require(n.communityId()));
    }
    public boolean eligible(DeliverySnapshot d, Instant at) {
        if (!accounts.eligible(d.approvedBy(), "ministries.communications.send") || d.templateVersion() == null
            || !"communities.notice".equals(d.templateKey())) {
            return false;
        }
        return reader.notice(d.communicationId())
            .filter(n -> n.deliveryId().equals(d.id()) && n.actorId().equals(d.approvedBy())
                && n.reviewHash().equals(d.reviewHash()) && n.contactId().equals(d.contactId()))
            .map(n -> {
                Membership m = reader.membership(n.membershipId()).orElse(null);
                Community c = reader.community(n.communityId()).orElse(null);
                return m != null && c != null && c.active() && m.covers(at) && m.communityId().equals(c.id())
                    && (accounts.eligible(d.approvedBy(), "ministries.manage")
                        || (accounts.eligible(d.approvedBy(), "ministries.lead")
                            && reader.leads(c.id(), d.approvedBy(), at)))
                    && people.get(m.personId()).map(PersonDirectory.Entry::available).orElse(false)
                    && emails.active(m.personId())
                        .stream()
                        .anyMatch(e -> e.id().equals(d.contactId()) && e.value().equals(d.recipient()))
                    && CommunityMessaging
                        .hash(m, c, d.contactId(), d.recipient(), d.subject(), d.body(), d.templateVersion())
                        .equals(d.reviewHash());
            })
            .orElse(false);
    }
}

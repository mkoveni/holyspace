package org.onechurch.communities.application.query.listcommunitydeliveries;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communities.application.CommunityMessaging;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;
public final class ListCommunityDeliveriesHandler {
    private final CommunityMessaging messaging;
    private final PermissionChecker auth;
    public ListCommunityDeliveriesHandler(CommunityMessaging messaging, PermissionChecker auth) {
        this.messaging = messaging;
        this.auth = auth;
    }
    public List<DeliveryReader.Summary> handle(ListCommunityDeliveriesQuery q) {
        auth.requirePermission("ministries.communications.send");
        return messaging.deliveries(q.communityId(), q.limit(), q.offset());
    }
}

package org.onechurch.volunteering.application.query.listvolunteerdeliveries;

import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.ServingViews;

public final class ListVolunteerDeliveriesHandler {
    private final PermissionChecker auth;
    private final DeliveryReader deliveries;
    public ListVolunteerDeliveriesHandler(PermissionChecker auth, DeliveryReader deliveries) {
        this.auth = auth;
        this.deliveries = deliveries;
    }
    public DeliveryReader.Page handle(ListVolunteerDeliveriesQuery q) {
        auth.requirePermission("volunteering.communications.send");
        ServingViews.page(q.limit(), q.offset());
        return deliveries.listForSource("volunteering", q.status(), q.limit(), q.offset());
    }
}

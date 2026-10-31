package org.onechurch.events.application.query.listeventdeliveries;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ListEventDeliveriesHandler {
    private final PermissionChecker auth;
    private final DeliveryReader deliveries;
    public ListEventDeliveriesHandler(PermissionChecker auth, DeliveryReader deliveries) {
        this.auth = auth;
        this.deliveries = deliveries;
    }
    public DeliveryReader.Page handle(ListEventDeliveriesQuery q) {
        auth.requirePermission("events.communications.send");
        org.onechurch.events.domain.EventRules.page(q.limit(), q.offset());
        return deliveries.listForSource("events", q.status(), q.limit(), q.offset());
    }
}

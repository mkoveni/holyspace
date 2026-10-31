package org.onechurch.childrenministry.messaging.application.query.getemaildelivery;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;
public final class GetEmailDeliveryHandler {
    public record View(DeliverySnapshot delivery, List<DeliveryReader.Event> events) {
        @Override
        public String toString() {
            return "DeliveryView[restricted]";
        }
    }
    private final PermissionChecker auth;
    private final DeliveryReader reader;
    public GetEmailDeliveryHandler(PermissionChecker auth, DeliveryReader reader) {
        this.auth = auth;
        this.reader = reader;
    }
    public View handle(GetEmailDeliveryQuery q) {
        auth.requirePermission("children.safeguarding");
        ChildrenRules.page(q.limit(), q.offset());
        DeliverySnapshot d = reader.delivery(q.deliveryId()).orElseThrow(() -> ChildrenException.missing("Delivery"));
        return new View(d, reader.events(d.id(), q.limit(), q.offset()));
    }
}

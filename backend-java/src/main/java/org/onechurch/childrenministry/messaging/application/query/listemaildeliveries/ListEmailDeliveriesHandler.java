package org.onechurch.childrenministry.messaging.application.query.listemaildeliveries;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;
public final class ListEmailDeliveriesHandler {
    private final PermissionChecker auth;
    private final DeliveryReader reader;
    public ListEmailDeliveriesHandler(PermissionChecker auth, DeliveryReader reader) {
        this.auth = auth;
        this.reader = reader;
    }
    public DeliveryReader.Page handle(ListEmailDeliveriesQuery q) {
        auth.requirePermission("children.safeguarding");
        ChildrenRules.page(q.limit(), q.offset());
        if (q.status() == null
            || !List
                .of("ALL", "QUEUED", "SCHEDULED", "SENDING", "SENT", "DELIVERED", "FAILED", "UNKNOWN", "CANCELLED",
                    "BLOCKED")
                .contains(q.status())) {
            throw ChildrenException.invalid("Choose a delivery status.");
        }
        return reader.list(q.status(), q.limit(), q.offset());
    }
}

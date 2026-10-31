package org.onechurch.childrenministry.messaging.infrastructure;
import org.onechurch.childrenministry.messaging.application.DeliveryPolicy;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.communications.application.contract.DeliveryEligibility;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.identityaccess.application.contract.ResponsibleAccountDirectory;
import org.springframework.stereotype.Component;

import java.time.Instant;
import java.util.UUID;
@Component
public final class GuardianDeliveryEligibility implements DeliveryEligibility {
    private final PermissionChecker auth;
    private final DeliveryPolicy policy;
    private final ResponsibleAccountDirectory accounts;
    public GuardianDeliveryEligibility(DeliveryPolicy policy, ResponsibleAccountDirectory accounts,
        PermissionChecker auth) {
        this.policy = policy;
        this.auth = auth;
        this.accounts = accounts;
    }
    public void authorize(DeliverySnapshot delivery, UUID actor, String operation) {
        if (operation.equals("receipt")) {
            auth.requirePermission(actor, "children.communications.receipts");
            return;
        }
        auth.requirePermission(actor, "children.communications.send");
        auth.requirePermission("children.safeguarding");
    }
    public String sourceContext() {
        return "children-ministry";
    }
    public boolean eligible(DeliverySnapshot delivery, Instant at) {
        if (!accounts.eligible(delivery.approvedBy(), "children.communications.send")
            || !accounts.eligible(delivery.approvedBy(), "children.safeguarding")) {
            return false;
        }
        try {
            policy.recheck(delivery, at);
            return true;
        } catch (ChildrenException rejected) {
            return false;
        }
    }
}

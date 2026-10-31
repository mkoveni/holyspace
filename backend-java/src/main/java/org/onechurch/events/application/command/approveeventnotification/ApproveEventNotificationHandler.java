package org.onechurch.events.application.command.approveeventnotification;
import org.onechurch.events.application.EventMessageApproval;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;
public final class ApproveEventNotificationHandler {
    private final PermissionChecker auth;
    private final EventMessageApproval approval;
    public ApproveEventNotificationHandler(PermissionChecker auth, EventMessageApproval approval) {
        this.auth = auth;
        this.approval = approval;
    }
    public UUID handle(ApproveEventNotificationCommand c) {
        auth.requirePermission(c.actorId(), "events.communications.send");
        return approval.approve(c.actorId(), c.requestId(), c.registrationId(), c.contactId(), c.purpose(), c.message(),
            c.reviewHash(), c.verificationEvidence());
    }
}

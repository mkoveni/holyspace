package org.onechurch.events.application.command.canceleventregistration;
import org.onechurch.events.application.EventRegistrationOperations;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class CancelEventRegistrationHandler {
    private final PermissionChecker auth;
    private final EventRegistrationOperations registrations;
    public CancelEventRegistrationHandler(PermissionChecker auth, EventRegistrationOperations registrations) {
        this.auth = auth;
        this.registrations = registrations;
    }
    public void handle(CancelEventRegistrationCommand c) {
        auth.requirePermission(c.actorId(), "events.registrations.manage");
        registrations.cancel(c.actorId(), c.registrationId(), c.expectedVersion(), c.reason());
    }
}

package org.onechurch.events.application.command.configureeventregistration;
import org.onechurch.events.application.EventRegistrationOperations;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ConfigureEventRegistrationHandler {
    private final PermissionChecker auth;
    private final EventRegistrationOperations registrations;
    public ConfigureEventRegistrationHandler(PermissionChecker auth, EventRegistrationOperations registrations) {
        this.auth = auth;
        this.registrations = registrations;
    }
    public void handle(ConfigureEventRegistrationCommand c) {
        auth.requirePermission(c.actorId(), "events.registrations.manage");
        registrations.configure(c.actorId(), c.occurrenceId(), c.expectedVersion(), c.open(), c.capacity(),
            c.waitlistEnabled(), c.reason());
    }
}

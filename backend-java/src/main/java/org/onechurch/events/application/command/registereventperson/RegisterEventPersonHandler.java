package org.onechurch.events.application.command.registereventperson;
import org.onechurch.events.application.EventRegistrationOperations;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;
public final class RegisterEventPersonHandler {
    private final PermissionChecker auth;
    private final EventRegistrationOperations registrations;
    public RegisterEventPersonHandler(PermissionChecker auth, EventRegistrationOperations registrations) {
        this.auth = auth;
        this.registrations = registrations;
    }
    public UUID handle(RegisterEventPersonCommand c) {
        auth.requirePermission(c.actorId(), "events.registrations.manage");
        return registrations.register(c.actorId(), c.requestId(), c.occurrenceId(), c.personId(), c.expectedVersion());
    }
}

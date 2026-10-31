package org.onechurch.events.application.command.createeventvenue;
import org.onechurch.events.application.VenueCatalogue;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;
public final class CreateEventVenueHandler {
    private final PermissionChecker auth;
    private final VenueCatalogue catalogue;
    public CreateEventVenueHandler(PermissionChecker auth, VenueCatalogue catalogue) {
        this.auth = auth;
        this.catalogue = catalogue;
    }
    public UUID handle(CreateEventVenueCommand c) {
        auth.requirePermission(c.actorId(), "events.manage");
        return catalogue.create(c.actorId(), c.name(), c.address(), c.exclusive());
    }
}

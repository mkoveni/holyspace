package org.onechurch.events.application.command.updateeventvenue;
import org.onechurch.events.application.VenueCatalogue;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class UpdateEventVenueHandler {
    private final PermissionChecker auth;
    private final VenueCatalogue catalogue;
    public UpdateEventVenueHandler(PermissionChecker auth, VenueCatalogue catalogue) {
        this.auth = auth;
        this.catalogue = catalogue;
    }
    public void handle(UpdateEventVenueCommand c) {
        auth.requirePermission(c.actorId(), "events.manage");
        catalogue.update(c.actorId(), c.venueId(), c.expectedVersion(), c.name(), c.address(), c.active());
    }
}

package org.onechurch.events.application.command.createchurchevent;
import org.onechurch.events.application.EventPlanning;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.UUID;
public final class CreateChurchEventHandler {
    private final PermissionChecker auth;
    private final EventPlanning planning;
    public CreateChurchEventHandler(PermissionChecker auth, EventPlanning planning) {
        this.auth = auth;
        this.planning = planning;
    }
    public UUID handle(CreateChurchEventCommand c) {
        auth.requirePermission(c.actorId(), "events.manage");
        return planning.create(c.actorId(), c.requestId(), c.title(), c.type(), c.description(), c.schedule(),
            c.previewHash());
    }
}

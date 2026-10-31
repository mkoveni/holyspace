package org.onechurch.events.application.query.geteventreadiness;
import org.onechurch.events.application.EventReadiness;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetEventReadinessHandler {
    private final PermissionChecker auth;
    private final EventReadiness service;
    public GetEventReadinessHandler(PermissionChecker auth, EventReadiness service) {
        this.auth = auth;
        this.service = service;
    }
    public EventReadiness.View handle(GetEventReadinessQuery q) {
        auth.requirePermission("events.manage");
        return service.view(q.occurrenceId());
    }
}

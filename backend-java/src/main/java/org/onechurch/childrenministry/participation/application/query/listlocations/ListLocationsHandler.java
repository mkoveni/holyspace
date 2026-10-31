package org.onechurch.childrenministry.participation.application.query.listlocations;

import org.onechurch.childrenministry.participation.domain.Location;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class ListLocationsHandler {
    private final PermissionChecker authorization;
    private final SessionReader session;
    public ListLocationsHandler(PermissionChecker authorization, SessionReader session) {
        this.authorization = authorization;
        this.session = session;
    }
    public List<Location> handle(ListLocationsQuery query) {
        authorization.requirePermission("children.read");
        ChildrenRules.page(query.limit(), query.offset());
        return session.locations(query.limit(), query.offset());
    }
}

package org.onechurch.childrenministry.participation.application.query.listrooms;

import org.onechurch.childrenministry.participation.domain.Room;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class ListRoomsHandler {
    private final PermissionChecker authorization;
    private final SessionReader session;
    public ListRoomsHandler(PermissionChecker authorization, SessionReader session) {
        this.authorization = authorization;
        this.session = session;
    }
    public List<Room> handle(ListRoomsQuery command) {
        authorization.requirePermission("children.read");
        return session.rooms(command.locationId());
    }
}

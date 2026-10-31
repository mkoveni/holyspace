package org.onechurch.identityaccess.application.query.listroles;

import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.AccessReader;

import java.util.List;

public final class ListRolesHandler {

    private final Authorization authorization;

    private final AccessReader access;

    public ListRolesHandler(AccessReader access, Authorization authorization) {
        this.authorization = authorization;
        this.access = access;
    }

    public List<AccessReader.Role> handle(ListRolesQuery query) {
        authorization.requirePermission("identity.manage");
        return access.roles();
    }
}

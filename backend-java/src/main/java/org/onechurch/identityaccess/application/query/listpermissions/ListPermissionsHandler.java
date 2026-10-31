package org.onechurch.identityaccess.application.query.listpermissions;

import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.AccessReader;

import java.util.List;

public final class ListPermissionsHandler {

    private final Authorization authorization;

    private final AccessReader access;

    public ListPermissionsHandler(AccessReader access, Authorization authorization) {
        this.authorization = authorization;
        this.access = access;
    }

    public List<AccessReader.Permission> handle(ListPermissionsQuery query) {
        authorization.requirePermission("identity.manage");
        return access.permissions();
    }
}

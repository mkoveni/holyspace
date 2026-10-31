package org.onechurch.identityaccess.application.contract;

import java.util.UUID;

public interface AccessWriter {

    void lockAccessChanges();

    void createRole(UUID id, String name, String description);

    void createPermission(UUID id, String code, String description);

    void assignRole(UUID accountId, UUID roleId, boolean assign);

    void assignPermission(UUID roleId, UUID permissionId, boolean assign);
}

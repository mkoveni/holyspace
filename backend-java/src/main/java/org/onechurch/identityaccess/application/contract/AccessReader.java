package org.onechurch.identityaccess.application.contract;

import java.util.List;
import java.util.Set;
import java.util.UUID;

public interface AccessReader {

    record Role(UUID id, String name, String description) {}

    record Permission(UUID id, String code, String description) {}

    List<Role> roles();

    List<Permission> permissions();

    Set<String> roleNames(UUID accountId);

    Set<String> permissionCodes(UUID accountId);

    int activeManagers();
}

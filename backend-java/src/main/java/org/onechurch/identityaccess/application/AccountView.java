package org.onechurch.identityaccess.application;

import org.onechurch.identityaccess.domain.Account;

import java.time.Instant;
import java.util.Set;
import java.util.UUID;

public record AccountView(UUID id, String username, String email, String displayName, UUID personId, String status,
    Set<String> roles, Set<String> permissions, Instant createdAt, Instant updatedAt) {

    public static AccountView from(Account account, Set<String> roles, Set<String> permissions) {
        return new AccountView(account.id(), account.username(), account.email(), account.displayName(), null,
            account.status(), roles, permissions, account.createdAt(), account.updatedAt());
    }
}

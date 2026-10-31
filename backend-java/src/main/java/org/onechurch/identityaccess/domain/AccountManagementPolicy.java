package org.onechurch.identityaccess.domain;

/** Cross-account invariant: a church must retain an active account manager. */
public final class AccountManagementPolicy {

    private AccountManagementPolicy() {
    }

    public static void requireActiveManager(int activeManagers) {
        if (activeManagers < 1) {
            throw new IdentityException(IdentityException.Kind.CONFLICT,
                "At least one active account manager must remain.");
        }
    }
}

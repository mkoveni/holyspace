package org.onechurch.identityaccess.domain.valueobject;

import org.onechurch.identityaccess.domain.IdentityException;

import java.util.Arrays;

public enum AccountStatus {
    ACTIVE("active"), DISABLED("disabled");

    private final String value;

    AccountStatus(String value) {
        this.value = value;
    }

    public String value() {
        return value;
    }

    public static AccountStatus from(String value) {
        return Arrays.stream(values())
            .filter(v -> v.value().equalsIgnoreCase(value))
            .findFirst()
            .orElseThrow(() -> new IdentityException(IdentityException.Kind.INVALID, "Invalid account status."));
    }
}

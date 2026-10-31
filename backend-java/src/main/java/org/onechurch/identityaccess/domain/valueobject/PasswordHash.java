package org.onechurch.identityaccess.domain.valueobject;

import org.onechurch.identityaccess.domain.IdentityException;

public record PasswordHash(String value) {

    public PasswordHash {
        if (value == null || value.isBlank() || value.length() > 255) {
            throw new IdentityException(IdentityException.Kind.INVALID, "A valid password hash is required.");
        }
    }

    @Override
    public String toString() {
        return "PasswordHash[redacted]";
    }
}

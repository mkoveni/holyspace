package org.onechurch.identityaccess.domain;

public class IdentityException extends RuntimeException {

    public enum Kind {
        INVALID, NOT_FOUND, CONFLICT, UNAUTHENTICATED, FORBIDDEN, THROTTLED
    }

    private final Kind kind;

    public IdentityException(Kind kind, String message) {
        super(message);
        this.kind = kind;
    }

    public Kind kind() {
        return kind;
    }
}

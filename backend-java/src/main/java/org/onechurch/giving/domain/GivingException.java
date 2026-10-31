package org.onechurch.giving.domain;
public final class GivingException extends RuntimeException {
    public enum Kind {
        INVALID, NOT_FOUND, CONFLICT, FORBIDDEN
    }
    private final Kind kind;
    public GivingException(Kind kind, String message) {
        super(message);
        this.kind = kind;
    }
    public Kind kind() {
        return kind;
    }
    public static GivingException invalid(String message) {
        return new GivingException(Kind.INVALID, message);
    }
    public static GivingException conflict(String message) {
        return new GivingException(Kind.CONFLICT, message);
    }
    public static GivingException forbidden() {
        return new GivingException(Kind.FORBIDDEN, "Verified donor access is required.");
    }
    public static GivingException missing() {
        return new GivingException(Kind.NOT_FOUND, "The Giving record was not found.");
    }
}

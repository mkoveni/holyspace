package org.onechurch.communications.domain;

public final class CommunicationException extends RuntimeException {
    public enum Kind {
        INVALID, NOT_FOUND, CONFLICT, FORBIDDEN, THROTTLED
    }
    private final Kind kind;
    public CommunicationException(Kind kind, String message) {
        super(message);
        this.kind = kind;
    }
    public Kind kind() {
        return kind;
    }
    public static CommunicationException invalid(String message) {
        return new CommunicationException(Kind.INVALID, message);
    }
    public static CommunicationException missing(String name) {
        return new CommunicationException(Kind.NOT_FOUND, name + " was not found.");
    }
    public static CommunicationException conflict(String message) {
        return new CommunicationException(Kind.CONFLICT, message);
    }
    public static CommunicationException forbidden() {
        return new CommunicationException(Kind.FORBIDDEN, "You are not allowed to perform this operation.");
    }
}

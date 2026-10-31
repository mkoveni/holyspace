package org.onechurch.events.domain;
public final class EventException extends RuntimeException {
    public enum Kind {
        INVALID, NOT_FOUND, CONFLICT
    }
    private final Kind kind;
    public EventException(Kind kind, String message) {
        super(message);
        this.kind = kind;
    }
    public Kind kind() {
        return kind;
    }
    public static EventException invalid(String message) {
        return new EventException(Kind.INVALID, message);
    }
    public static EventException missing(String name) {
        return new EventException(Kind.NOT_FOUND, name + " was not found.");
    }
    public static EventException conflict(String message) {
        return new EventException(Kind.CONFLICT, message);
    }
}

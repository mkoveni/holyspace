package org.onechurch.people.domain;

public final class PeopleException extends RuntimeException {

    public enum Kind {
        INVALID, NOT_FOUND, CONFLICT
    }
    private final Kind kind;

    public PeopleException(Kind kind, String message) {
        super(message);
        this.kind = kind;
    }

    public Kind kind() {
        return kind;
    }

    public static PeopleException invalid(String message) {
        return new PeopleException(Kind.INVALID, message);
    }

    public static PeopleException missing(String name) {
        return new PeopleException(Kind.NOT_FOUND, name + " was not found.");
    }

    public static PeopleException conflict(String message) {
        return new PeopleException(Kind.CONFLICT, message);
    }
}

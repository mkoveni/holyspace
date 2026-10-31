package org.onechurch.volunteering.domain;

public final class VolunteerException extends RuntimeException {
    public enum Kind {
        INVALID, NOT_FOUND, CONFLICT, FORBIDDEN
    }
    private final Kind kind;
    public VolunteerException(Kind kind, String message) {
        super(message);
        this.kind = kind;
    }
    public Kind kind() {
        return kind;
    }
    public static VolunteerException invalid(String message) {
        return new VolunteerException(Kind.INVALID, message);
    }
    public static VolunteerException missing(String name) {
        return new VolunteerException(Kind.NOT_FOUND, name + " was not found.");
    }
    public static VolunteerException conflict(String message) {
        return new VolunteerException(Kind.CONFLICT, message);
    }
    public static VolunteerException forbidden() {
        return new VolunteerException(Kind.FORBIDDEN, "You cannot perform this volunteer action.");
    }
}

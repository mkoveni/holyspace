package org.onechurch.childrenministry.shared.domain;

public final class ChildrenException extends RuntimeException {
    public enum Kind {
        INVALID, NOT_FOUND, CONFLICT, FORBIDDEN, THROTTLED
    }
    private final Kind kind;
    public ChildrenException(Kind kind, String message) {
        super(message);
        this.kind = kind;
    }
    public Kind kind() {
        return kind;
    }
    public static ChildrenException invalid(String message) {
        return new ChildrenException(Kind.INVALID, message);
    }
    public static ChildrenException missing(String name) {
        return new ChildrenException(Kind.NOT_FOUND, name + " was not found.");
    }
    public static ChildrenException conflict(String message) {
        return new ChildrenException(Kind.CONFLICT, message);
    }
    public static ChildrenException forbidden() {
        return new ChildrenException(Kind.FORBIDDEN, "This operation is outside your assigned session.");
    }
}

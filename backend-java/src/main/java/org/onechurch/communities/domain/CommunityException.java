package org.onechurch.communities.domain;
public final class CommunityException extends RuntimeException {
    public enum Kind {
        INVALID, NOT_FOUND, CONFLICT, FORBIDDEN
    }
    private final Kind kind;
    public CommunityException(Kind kind, String message) {
        super(message);
        this.kind = kind;
    }
    public Kind kind() {
        return kind;
    }
    public static CommunityException invalid(String text) {
        return new CommunityException(Kind.INVALID, text);
    }
    public static CommunityException conflict(String text) {
        return new CommunityException(Kind.CONFLICT, text);
    }
    public static CommunityException missing() {
        return new CommunityException(Kind.NOT_FOUND, "The requested community record was not found.");
    }
}

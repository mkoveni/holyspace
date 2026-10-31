package org.onechurch.people.domain;

public enum RelationshipType {
    PARENT_OF, GUARDIAN_OF, SPOUSE_OF, SIBLING_OF, OTHER;

    public boolean symmetric() {
        return this == SPOUSE_OF || this == SIBLING_OF || this == OTHER;
    }

    public String inverse() {
        return switch (this) {
            case PARENT_OF -> "CHILD_OF";
            case GUARDIAN_OF -> "WARD_OF";
            default -> name();
        };
    }
}

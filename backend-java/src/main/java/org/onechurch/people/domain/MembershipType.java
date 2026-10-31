package org.onechurch.people.domain;

public enum MembershipType {
    ACTIVE_MEMBER("Active Member"), REGULAR_VISITOR("Regular Visitor"), VISITOR("Visitor"), TRANSFERRED("Transferred");

    private final String label;

    MembershipType(String label) {
        this.label = label;
    }

    public String label() {
        return label;
    }
}

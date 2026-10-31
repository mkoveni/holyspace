package org.onechurch.childrenministry.sessions.domain;

public final class CapacityPolicy {
    private CapacityPolicy() {
    }
    public static int capacity(int seats, int roomCapacity, int eligiblePresent, boolean leaderPresent,
        int minimumStaff, int childrenPerStaff) {
        if (eligiblePresent < minimumStaff || !leaderPresent) {
            return 0;
        }
        return (int) Math.min(Math.min((long) seats, roomCapacity), (long) eligiblePresent * childrenPerStaff);
    }
}

package org.onechurch.volunteering.application.contract;

/** Published failure; consumers never depend on the originating domain exception. */
public final class StaffingUnavailable extends RuntimeException {
    public StaffingUnavailable() {
        super("Volunteer staffing is unavailable.");
    }
}

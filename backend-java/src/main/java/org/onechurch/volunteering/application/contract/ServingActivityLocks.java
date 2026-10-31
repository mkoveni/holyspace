package org.onechurch.volunteering.application.contract;

import java.util.List;
import java.util.UUID;

/** Originating contexts serialize staffing eligibility changes with attendance decisions. */
public interface ServingActivityLocks {
    record Activity(String kind, UUID id) {}
    void lock(List<Activity> activities);
}

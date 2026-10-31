package org.onechurch.childrenministry.safeguarding.application;
import org.onechurch.childrenministry.participation.domain.Enrolment;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
public record ReadinessView(boolean ready, int completed, int total, LocalDate on, Instant checkedAt, List<Step> steps,
    Enrolment enrolment, String timeZone) {
    public record Step(String code, boolean complete) {}
    public record Summary(boolean ready, int completed, int total) {}
    public Summary summary() {
        return new Summary(ready, completed, total);
    }
}

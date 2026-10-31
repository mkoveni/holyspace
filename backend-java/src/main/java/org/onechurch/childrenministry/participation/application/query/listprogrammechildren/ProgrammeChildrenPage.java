package org.onechurch.childrenministry.participation.application.query.listprogrammechildren;
import org.onechurch.childrenministry.safeguarding.application.ReadinessView.Summary;

import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public record ProgrammeChildrenPage(List<Child> children, long total) {
    public record Child(UUID enrolmentId, UUID childId, String name, LocalDate validFrom, LocalDate validUntil,
        String status, List<Placement> classes, Summary readiness) {}
    public record Placement(UUID id, String name) {}
}

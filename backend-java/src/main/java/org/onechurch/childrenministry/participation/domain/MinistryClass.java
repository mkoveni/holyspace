package org.onechurch.childrenministry.participation.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.LocalDate;
import java.util.UUID;
public record MinistryClass(UUID id, UUID programmeId, String name, int minAgeMonths, int maxAgeMonths) {
    public MinistryClass {
        name = ChildrenRules.text(name, 120);
        if (minAgeMonths < 0 || maxAgeMonths <= minAgeMonths || maxAgeMonths > 216) {
            throw ChildrenException
                .invalid("Class age range must be within 0–216 months, with an exclusive upper bound.");
        }
    }
    public void requireAge(LocalDate birth, LocalDate on) {
        ChildrenRules.child(birth, on);
        if (birth.plusMonths(minAgeMonths).isAfter(on) || !birth.plusMonths(maxAgeMonths).isAfter(on)) {
            throw ChildrenException.invalid("Child is outside this class's age range.");
        }
    }
}

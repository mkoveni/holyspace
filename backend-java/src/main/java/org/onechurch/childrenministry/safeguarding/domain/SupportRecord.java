package org.onechurch.childrenministry.safeguarding.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;
public record SupportRecord(UUID childId, String allergies, String careInstructions, String restrictedNotes,
    LocalDate reviewOn, LocalDate retainUntil, long version, Instant updatedAt) {
    public SupportRecord {
        updatedAt = ChildrenRules.instant(updatedAt);
        allergies = ChildrenRules.optional(allergies, 2000);
        careInstructions = ChildrenRules.optional(careInstructions, 2000);
        restrictedNotes = ChildrenRules.optional(restrictedNotes, 2000);
        if (reviewOn == null || retainUntil == null || retainUntil.isBefore(reviewOn)) {
            throw ChildrenException.invalid("Retention date must not precede review date.");
        }
    }
    @Override
    public String toString() {
        return "SupportRecord[childId=" + childId + ", version=" + version + "]";
    }
}

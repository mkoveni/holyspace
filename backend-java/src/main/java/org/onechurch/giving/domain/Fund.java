package org.onechurch.giving.domain;
import java.util.UUID;
public record Fund(UUID id, String name, String description, boolean active, long version) {
    public Fund {
        name = GivingRules.text(name, 100, "Fund name");
        description = description == null ? "" : description.trim();
        if (id == null || description.length() > 1000 || version < 0) {
            throw GivingException.invalid("Invalid fund details.");
        }
    }
    public Fund revise(long expectedVersion, String newName, String newDescription, boolean enabled) {
        GivingRules.version(version, expectedVersion);
        return new Fund(id, newName, newDescription, enabled, version + 1);
    }
}

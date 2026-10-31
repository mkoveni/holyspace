package org.onechurch.communities.domain;
import java.util.Set;
import java.util.UUID;
public record Community(UUID id, String kind, String name, String description, UUID parentId, String campus,
    boolean active, long version) {
    public Community {
        if (!Set.of("MINISTRY", "GROUP").contains(kind) || (parentId != null && !kind.equals("GROUP"))) {
            throw CommunityException.invalid("Choose a ministry or a group with an optional parent ministry.");
        }
        name = CommunityRules.text(name, 150);
        description = CommunityRules.optional(description, 5000);
        campus = CommunityRules.optional(campus, 150);
    }
    public Community update(String name, String description, UUID parent, String campus, boolean active) {
        return new Community(id, kind, name, description, parent, campus, active, version + 1);
    }
}

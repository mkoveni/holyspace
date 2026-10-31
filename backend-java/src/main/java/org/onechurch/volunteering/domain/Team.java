package org.onechurch.volunteering.domain;

import java.util.UUID;

public record Team(UUID id, String name, boolean active, long version) {
    public Team {
        ServingRules.id(id);
        name = ServingRules.text(name, 150);
    }
    public Team change(String name, boolean active) {
        return new Team(id, name, active, version + 1);
    }
    public Team touch() {
        return new Team(id, name, active, version + 1);
    }
}

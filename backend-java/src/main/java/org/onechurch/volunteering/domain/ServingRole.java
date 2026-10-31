package org.onechurch.volunteering.domain;

import java.util.List;
import java.util.Objects;
import java.util.Set;
import java.util.UUID;

public record ServingRole(UUID id, UUID teamId, String name, String childrenRole, boolean active,
    List<UUID> requiredCredentials) {
    public ServingRole {
        ServingRules.id(id);
        ServingRules.id(teamId);
        name = ServingRules.text(name, 100);
        if (childrenRole == null || !Set.of("NONE", "LEADER", "ASSISTANT").contains(childrenRole)) {
            throw VolunteerException.invalid("Choose a children responsibility or none.");
        }
        if (requiredCredentials == null || requiredCredentials.stream().anyMatch(Objects::isNull)) {
            throw VolunteerException.invalid("Choose credential requirements from the catalogue.");
        }
        requiredCredentials = List.copyOf(requiredCredentials);
        if (requiredCredentials.size() > 30
            || requiredCredentials.stream().distinct().count() != requiredCredentials.size()) {
            throw VolunteerException.invalid("Choose at most 30 unique credential requirements.");
        }
    }
}

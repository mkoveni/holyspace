package org.onechurch.volunteering.domain;

import java.util.UUID;

public record CredentialType(UUID id, String code, String name) {
    public CredentialType {
        ServingRules.id(id);
        code = ServingRules.text(code, 80);
        if (!code.matches("[a-z][a-z0-9.-]{1,79}")) {
            throw VolunteerException.invalid("Use a lowercase credential code.");
        }
        name = ServingRules.text(name, 150);
    }
}

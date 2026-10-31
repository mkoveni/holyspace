package org.onechurch.identityaccess.domain.valueobject;

import org.onechurch.identityaccess.domain.IdentityRules;

public record DisplayName(String value) {

    public DisplayName {
        value = IdentityRules.name(value);
    }
}

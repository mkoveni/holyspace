package org.onechurch.identityaccess.domain.valueobject;

import org.onechurch.identityaccess.domain.IdentityRules;

public record Username(String value) {

    public Username {
        value = IdentityRules.username(value);
    }
}

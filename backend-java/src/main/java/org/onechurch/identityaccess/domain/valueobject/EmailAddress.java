package org.onechurch.identityaccess.domain.valueobject;

import org.onechurch.identityaccess.domain.IdentityRules;

public record EmailAddress(String value) {

    public EmailAddress {
        value = IdentityRules.email(value);
    }
}

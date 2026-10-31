package org.onechurch.identityaccess.application.query.listmemberaccess;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class ListMemberAccessHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListMemberAccessHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListMemberAccessQuery(25, 0);
    }
}

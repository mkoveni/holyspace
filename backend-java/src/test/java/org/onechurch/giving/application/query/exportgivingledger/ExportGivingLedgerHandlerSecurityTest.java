package org.onechurch.giving.application.query.exportgivingledger;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class ExportGivingLedgerHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ExportGivingLedgerHandler.class;
    }
    protected Object input(UUID actor) {
        return new ExportGivingLedgerQuery(null);
    }

}

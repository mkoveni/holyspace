package org.onechurch.childrenministry.sessions.application.query.listmychildattendance;
import org.onechurch.shared.infrastructure.DonorHandlerSecuritySupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.UUID;
class ListMyChildAttendanceHandlerSecurityTest extends DonorHandlerSecuritySupport {
    @Autowired ListMyChildAttendanceHandler handler;
    @Override
    protected Object invoke(UUID actor) {
        handler.handle(new ListMyChildAttendanceQuery(UUID.randomUUID(), 10, 0));
        return null;
    }
}

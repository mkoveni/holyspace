package org.onechurch.giving.application.command.issueacknowledgement;
import org.onechurch.shared.infrastructure.GivingHandlerSecuritySupport;

import java.util.UUID;
class IssueAcknowledgementHandlerSecurityTest extends GivingHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return IssueAcknowledgementHandler.class;
    }
    protected Object input(UUID actor) {
        return new IssueAcknowledgementCommand(actor, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}

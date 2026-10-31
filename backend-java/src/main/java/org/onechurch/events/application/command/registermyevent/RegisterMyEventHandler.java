package org.onechurch.events.application.command.registermyevent;
import org.onechurch.events.application.MemberEventActions;
import org.onechurch.identityaccess.application.contract.MemberIdentity;

import java.util.UUID;
public final class RegisterMyEventHandler {
    private final MemberEventActions actions;
    private final MemberIdentity auth;
    public RegisterMyEventHandler(MemberEventActions actions, MemberIdentity auth) {
        this.actions = actions;
        this.auth = auth;
    }
    public UUID handle(RegisterMyEventCommand c) {
        auth.requirePerson(c.actorId());
        return actions.register(c.actorId(), c.requestId(), c.occurrenceId(), c.expectedVersion());
    }
}

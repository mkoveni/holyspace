package org.onechurch.events.application.command.cancelmyevent;
import org.onechurch.events.application.MemberEventActions;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
public final class CancelMyEventHandler {
    private final MemberEventActions actions;
    private final MemberIdentity auth;
    public CancelMyEventHandler(MemberEventActions actions, MemberIdentity auth) {
        this.actions = actions;
        this.auth = auth;
    }
    public void handle(CancelMyEventCommand c) {
        auth.requirePerson(c.actorId());
        actions.cancel(c.actorId(), c.occurrenceId(), c.expectedVersion());
    }
}

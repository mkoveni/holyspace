package org.onechurch.events.application.command.issuemyeventpass;
import org.onechurch.events.application.MemberEventActions;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
public final class IssueMyEventPassHandler {
    private final MemberEventActions actions;
    private final MemberIdentity auth;
    public IssueMyEventPassHandler(MemberEventActions actions, MemberIdentity auth) {
        this.actions = actions;
        this.auth = auth;
    }
    public MemberEventActions.Pass handle(IssueMyEventPassCommand c) {
        auth.requirePerson(c.actorId());
        return actions.issue(c.actorId(), c.occurrenceId());
    }
}

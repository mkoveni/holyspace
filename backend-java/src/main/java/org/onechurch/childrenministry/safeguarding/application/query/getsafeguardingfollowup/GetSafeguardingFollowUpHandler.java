package org.onechurch.childrenministry.safeguarding.application.query.getsafeguardingfollowup;
import org.onechurch.childrenministry.safeguarding.application.SafeguardingFollowUp;
import org.onechurch.childrenministry.safeguarding.application.SafeguardingFollowUpView;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetSafeguardingFollowUpHandler {
    private final PermissionChecker authorization;
    private final SafeguardingFollowUp followUp;
    public GetSafeguardingFollowUpHandler(PermissionChecker authorization, SafeguardingFollowUp followUp) {
        this.authorization = authorization;
        this.followUp = followUp;
    }
    public SafeguardingFollowUpView handle(GetSafeguardingFollowUpQuery query) {
        authorization.requirePermission("children.safeguarding");
        boolean care = authorization.hasPermission("children.support");
        if ("CARE".equals(query.kind())) {
            authorization.requirePermission("children.support");
        }
        return followUp.list(query.programmeId(), query.search(), query.kind(), query.state(), query.days(),
            query.limit(), query.offset(), care);
    }
}

package org.onechurch.childrenministry.safeguarding.application.query.getsafeguarding;

import org.onechurch.childrenministry.safeguarding.application.SafeguardingView;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetSafeguardingHandler {
    private final PermissionChecker authorization;
    private final SafeguardingReader safeguarding;
    public GetSafeguardingHandler(PermissionChecker authorization, SafeguardingReader safeguarding) {
        this.authorization = authorization;
        this.safeguarding = safeguarding;
    }
    public SafeguardingView handle(GetSafeguardingQuery command) {
        authorization.requirePermission("children.safeguarding");
        return new SafeguardingView(safeguarding.authorities(command.childId()),
            safeguarding.consents(command.childId()), safeguarding.authorizations(command.childId()));
    }
}

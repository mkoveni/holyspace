package org.onechurch.childrenministry.incidents.application.query.listincidentowners;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.identityaccess.application.contract.ResponsibleAccountDirectory;

import java.util.List;
public final class ListIncidentOwnersHandler {
    private final PermissionChecker auth;
    private final ResponsibleAccountDirectory accounts;
    public ListIncidentOwnersHandler(PermissionChecker auth, ResponsibleAccountDirectory accounts) {
        this.auth = auth;
        this.accounts = accounts;
    }
    public List<ResponsibleAccountDirectory.Entry> handle(ListIncidentOwnersQuery q) {
        auth.requirePermission("children.incidents.manage");
        return accounts.search("children.incidents.manage", ChildrenRules.optional(q.search(), 120));
    }
}

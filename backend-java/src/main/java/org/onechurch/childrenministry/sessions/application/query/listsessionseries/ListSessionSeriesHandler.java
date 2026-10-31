package org.onechurch.childrenministry.sessions.application.query.listsessionseries;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPage;
import org.onechurch.childrenministry.sessions.application.contract.SessionSeriesReader;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ListSessionSeriesHandler {
    private final PermissionChecker authorization;
    private final SessionSeriesReader reader;
    public ListSessionSeriesHandler(PermissionChecker authorization, SessionSeriesReader reader) {
        this.authorization = authorization;
        this.reader = reader;
    }
    public SessionSeriesPage handle(ListSessionSeriesQuery query) {
        authorization.requirePermission("children.read");
        ChildrenRules.page(query.limit(), query.offset());
        return reader.list(query.limit(), query.offset());
    }
}

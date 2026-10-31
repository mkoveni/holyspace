package org.onechurch.childrenministry.sessions.application.query.getsessionseries;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPage;
import org.onechurch.childrenministry.sessions.application.contract.SessionSeriesReader;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetSessionSeriesHandler {
    private final PermissionChecker authorization;
    private final SessionSeriesReader reader;
    public GetSessionSeriesHandler(PermissionChecker authorization, SessionSeriesReader reader) {
        this.authorization = authorization;
        this.reader = reader;
    }
    public SessionSeriesPage.Detail handle(GetSessionSeriesQuery query) {
        authorization.requirePermission("children.read");

        return new SessionSeriesPage.Detail(
            reader.summary(query.id()).orElseThrow(() -> ChildrenException.missing("Series")),
            reader.sessions(query.id()));
    }
}

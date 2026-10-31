package org.onechurch.childrenministry.sessions.application.query.previewsessionseries;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPlanning;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPreview;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class PreviewSessionSeriesHandler {
    private final PermissionChecker authorization;
    private final SessionSeriesPlanning planning;
    public PreviewSessionSeriesHandler(PermissionChecker authorization, SessionSeriesPlanning planning) {
        this.authorization = authorization;
        this.planning = planning;
    }
    public SessionSeriesPreview handle(PreviewSessionSeriesQuery query) {
        authorization.requirePermission("children.manage");

        return planning.preview(query.draft());
    }
}

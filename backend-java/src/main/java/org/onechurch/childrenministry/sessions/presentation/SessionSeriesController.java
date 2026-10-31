package org.onechurch.childrenministry.sessions.presentation;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPage;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPreview;
import org.onechurch.childrenministry.sessions.application.command.cancelsessionseries.CancelSessionSeriesCommand;
import org.onechurch.childrenministry.sessions.application.command.cancelsessionseries.CancelSessionSeriesHandler;
import org.onechurch.childrenministry.sessions.application.command.createsessionseries.CreateSessionSeriesCommand;
import org.onechurch.childrenministry.sessions.application.command.createsessionseries.CreateSessionSeriesHandler;
import org.onechurch.childrenministry.sessions.application.query.getsessionseries.GetSessionSeriesHandler;
import org.onechurch.childrenministry.sessions.application.query.getsessionseries.GetSessionSeriesQuery;
import org.onechurch.childrenministry.sessions.application.query.listsessionseries.ListSessionSeriesHandler;
import org.onechurch.childrenministry.sessions.application.query.listsessionseries.ListSessionSeriesQuery;
import org.onechurch.childrenministry.sessions.application.query.previewsessionseries.PreviewSessionSeriesHandler;
import org.onechurch.childrenministry.sessions.application.query.previewsessionseries.PreviewSessionSeriesQuery;
import org.onechurch.childrenministry.sessions.presentation.api.SessionSeriesApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class SessionSeriesController implements SessionSeriesApi {
    private final PreviewSessionSeriesHandler preview;
    private final CreateSessionSeriesHandler create;
    private final ListSessionSeriesHandler list;
    private final GetSessionSeriesHandler get;
    private final CancelSessionSeriesHandler cancel;
    public SessionSeriesController(PreviewSessionSeriesHandler preview, CreateSessionSeriesHandler create,
        ListSessionSeriesHandler list, GetSessionSeriesHandler get, CancelSessionSeriesHandler cancel) {
        this.preview = preview;
        this.create = create;
        this.list = list;
        this.get = get;
        this.cancel = cancel;
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public SessionSeriesPreview preview(Draft body) {
        return preview.handle(new PreviewSessionSeriesQuery(body.toDraft()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public Created create(Create body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(create.handle(new CreateSessionSeriesCommand(UUID.fromString(principal.getName()),
            body.requestId(), body.draft().toDraft())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public SessionSeriesPage list(int limit, int offset) {
        return list.handle(new ListSessionSeriesQuery(limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public SessionSeriesPage.Detail get(UUID id) {
        return get.handle(new GetSessionSeriesQuery(id));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public void cancel(UUID id, Cancel body, OAuth2AuthenticatedPrincipal principal) {
        cancel.handle(new CancelSessionSeriesCommand(UUID.fromString(principal.getName()), id, body.expectedVersion(),
            body.reason()));
    }
}

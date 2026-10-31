package org.onechurch.communications.application.query.listtemplaterevisions;
import org.onechurch.communications.application.contract.TemplateReader;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class ListTemplateRevisionsHandler {
    private final PermissionChecker auth;
    private final TemplateReader reader;
    public ListTemplateRevisionsHandler(PermissionChecker auth, TemplateReader reader) {
        this.auth = auth;
        this.reader = reader;
    }
    public List<TemplateReader.Revision> handle(ListTemplateRevisionsQuery q) {
        auth.requirePermission("communications.templates.read");
        if (q.limit() < 1 || q.limit() > 100 || q.offset() < 0 || q.offset() > 100000) {
            throw CommunicationException.invalid("Choose a bounded revision page.");
        }
        reader.find(q.id()).orElseThrow(() -> CommunicationException.missing("Template"));
        return reader.revisions(q.id(), q.limit(), q.offset());
    }
}

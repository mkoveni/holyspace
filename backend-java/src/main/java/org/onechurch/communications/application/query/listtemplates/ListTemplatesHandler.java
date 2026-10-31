package org.onechurch.communications.application.query.listtemplates;
import org.onechurch.communications.application.contract.TemplateReader;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class ListTemplatesHandler {
    private final PermissionChecker auth;
    private final TemplateReader reader;
    public ListTemplatesHandler(PermissionChecker auth, TemplateReader reader) {
        this.auth = auth;
        this.reader = reader;
    }
    public TemplateReader.Page handle(ListTemplatesQuery q) {
        auth.requirePermission("communications.templates.read");
        if (q.limit() < 1 || q.limit() > 100 || q.offset() < 0 || q.offset() > 100000 || q.search() == null
            || q.search().length() > 150) {
            throw CommunicationException.invalid("Choose a bounded template page.");
        }
        return reader.list(q.search(), q.limit(), q.offset());
    }
}

package org.onechurch.communications.application.query.gettemplate;
import org.onechurch.communications.application.contract.TemplateReader;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class GetTemplateHandler {
    private final PermissionChecker auth;
    private final TemplateReader reader;
    public GetTemplateHandler(PermissionChecker auth, TemplateReader reader) {
        this.auth = auth;
        this.reader = reader;
    }
    public TemplateReader.View handle(GetTemplateQuery q) {
        auth.requirePermission("communications.templates.read");
        return reader.find(q.id()).orElseThrow(() -> CommunicationException.missing("Template"));
    }
}

package org.onechurch.communications.application.query.previewtemplate;
import org.onechurch.communications.application.contract.RichText;
import org.onechurch.communications.domain.TemplateContent;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class PreviewTemplateHandler {
    private final PermissionChecker auth;
    private final RichText richText;
    public PreviewTemplateHandler(PermissionChecker auth, RichText richText) {
        this.auth = auth;
        this.richText = richText;
    }
    public TemplateContent.Rendered handle(PreviewTemplateQuery q) {
        auth.requirePermission("communications.templates.read");
        TemplateContent content = new TemplateContent(q.subject(), richText.sanitize(q.html()), q.variables());
        TemplateContent.Rendered result = content.render(q.values());
        return new TemplateContent.Rendered(result.subject(), richText.sanitize(result.html()));
    }
}

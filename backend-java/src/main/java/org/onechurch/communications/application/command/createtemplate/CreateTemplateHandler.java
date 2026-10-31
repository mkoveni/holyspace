package org.onechurch.communications.application.command.createtemplate;
import org.onechurch.communications.application.contract.CommunicationAudit;
import org.onechurch.communications.application.contract.RichText;
import org.onechurch.communications.domain.EmailTemplate;
import org.onechurch.communications.domain.TemplateContent;
import org.onechurch.communications.domain.repository.TemplateRepository;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;
public final class CreateTemplateHandler {
    private final PermissionChecker auth;
    private final TemplateRepository repository;
    private final RichText richText;
    private final UnitOfWork tx;
    private final CommunicationAudit audit;
    private final Clock clock;
    public CreateTemplateHandler(PermissionChecker auth, TemplateRepository repository, RichText richText,
        UnitOfWork tx, CommunicationAudit audit, Clock clock) {
        this.auth = auth;
        this.repository = repository;
        this.richText = richText;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
    }
    public UUID handle(CreateTemplateCommand c) {
        auth.requirePermission(c.actorId(), "communications.templates.manage");
        return tx.execute(() -> {
            TemplateContent content = new TemplateContent(c.subject(), richText.sanitize(c.html()), c.variables());
            EmailTemplate template = new EmailTemplate(UUID.randomUUID(), c.key(), c.name(), content, "DRAFT", 0,
                c.actorId(), clock.instant(), null);
            repository.insert(template);
            audit.record(c.actorId(), "template.createtemplate", template.id(), "", clock.instant());
            return template.id();
        });
    }
}

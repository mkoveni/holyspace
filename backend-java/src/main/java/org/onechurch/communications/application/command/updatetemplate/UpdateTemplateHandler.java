package org.onechurch.communications.application.command.updatetemplate;
import org.onechurch.communications.application.contract.CommunicationAudit;
import org.onechurch.communications.application.contract.RichText;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.CommunicationRules;
import org.onechurch.communications.domain.EmailTemplate;
import org.onechurch.communications.domain.TemplateContent;
import org.onechurch.communications.domain.repository.TemplateRepository;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;
public final class UpdateTemplateHandler {
    private final PermissionChecker auth;
    private final TemplateRepository repository;
    private final RichText richText;
    private final UnitOfWork tx;
    private final CommunicationAudit audit;
    private final Clock clock;
    public UpdateTemplateHandler(PermissionChecker auth, TemplateRepository repository, RichText richText,
        UnitOfWork tx, CommunicationAudit audit, Clock clock) {
        this.auth = auth;
        this.repository = repository;
        this.richText = richText;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
    }
    public UUID handle(UpdateTemplateCommand c) {
        auth.requirePermission(c.actorId(), "communications.templates.manage");
        return tx.execute(() -> {
            EmailTemplate current =
                repository.lock(c.id()).orElseThrow(() -> CommunicationException.missing("Template"));
            CommunicationRules.version(current.version(), c.expectedVersion());
            EmailTemplate template =
                current.edit(c.name(), new TemplateContent(c.subject(), richText.sanitize(c.html()), c.variables()),
                    c.actorId(), clock.instant());
            repository.save(template);
            audit.record(c.actorId(), "template.updatetemplate", template.id(), "", clock.instant());
            return template.id();
        });
    }
}

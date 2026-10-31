package org.onechurch.communications.application.command.archivetemplate;
import org.onechurch.communications.application.contract.CommunicationAudit;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.CommunicationRules;
import org.onechurch.communications.domain.EmailTemplate;
import org.onechurch.communications.domain.repository.TemplateRepository;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;
public final class ArchiveTemplateHandler {
    private final PermissionChecker auth;
    private final TemplateRepository repository;
    private final UnitOfWork tx;
    private final CommunicationAudit audit;
    private final Clock clock;
    public ArchiveTemplateHandler(PermissionChecker auth, TemplateRepository repository, UnitOfWork tx,
        CommunicationAudit audit, Clock clock) {
        this.auth = auth;
        this.repository = repository;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
    }
    public UUID handle(ArchiveTemplateCommand c) {
        auth.requirePermission(c.actorId(), "communications.templates.manage");
        return tx.execute(() -> {
            EmailTemplate current =
                repository.lock(c.id()).orElseThrow(() -> CommunicationException.missing("Template"));
            CommunicationRules.version(current.version(), c.expectedVersion());
            EmailTemplate template = current.archive(c.actorId(), clock.instant());
            repository.save(template);
            audit.record(c.actorId(), "template.archivetemplate", template.id(), "", clock.instant());
            return template.id();
        });
    }
}

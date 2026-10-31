package org.onechurch.communications.application.command.publishtemplate;
import org.onechurch.communications.application.contract.CommunicationAudit;
import org.onechurch.communications.application.contract.TemplatePublicationPolicy;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.CommunicationRules;
import org.onechurch.communications.domain.EmailTemplate;
import org.onechurch.communications.domain.repository.TemplateRepository;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.List;
import java.util.UUID;
public final class PublishTemplateHandler {
    private final List<TemplatePublicationPolicy> policies;
    private final PermissionChecker auth;
    private final TemplateRepository repository;
    private final UnitOfWork tx;
    private final CommunicationAudit audit;
    private final Clock clock;
    public PublishTemplateHandler(PermissionChecker auth, TemplateRepository repository, UnitOfWork tx,
        CommunicationAudit audit, Clock clock, List<TemplatePublicationPolicy> policies) {
        this.auth = auth;
        this.policies = List.copyOf(policies);
        this.repository = repository;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
    }
    public UUID handle(PublishTemplateCommand c) {
        auth.requirePermission(c.actorId(), "communications.templates.manage");
        return tx.execute(() -> {
            EmailTemplate current =
                repository.lock(c.id()).orElseThrow(() -> CommunicationException.missing("Template"));
            CommunicationRules.version(current.version(), c.expectedVersion());
            EmailTemplate template = current.publish(c.actorId(), clock.instant());
            for (TemplatePublicationPolicy policy : policies) {
                policy
                    .violation(template.key(), template.content().subject(), template.content().html(),
                        template.content().variables())
                    .ifPresent(problem -> {
                        throw CommunicationException.invalid(problem);
                    });
            }
            repository.save(template);
            repository.publish(template);
            audit.record(c.actorId(), "template.publishtemplate", template.id(), "", clock.instant());
            return template.id();
        });
    }
}

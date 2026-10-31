package org.onechurch.communications.infrastructure;
import org.onechurch.communications.application.PublishedEmailTemplates;
import org.onechurch.communications.application.command.archivetemplate.ArchiveTemplateHandler;
import org.onechurch.communications.application.command.createtemplate.CreateTemplateHandler;
import org.onechurch.communications.application.command.publishtemplate.PublishTemplateHandler;
import org.onechurch.communications.application.command.updatetemplate.UpdateTemplateHandler;
import org.onechurch.communications.application.contract.CommunicationAudit;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.communications.application.contract.RichText;
import org.onechurch.communications.application.contract.TemplatePublicationPolicy;
import org.onechurch.communications.application.contract.TemplateReader;
import org.onechurch.communications.application.query.gettemplate.GetTemplateHandler;
import org.onechurch.communications.application.query.listtemplaterevisions.ListTemplateRevisionsHandler;
import org.onechurch.communications.application.query.listtemplates.ListTemplatesHandler;
import org.onechurch.communications.application.query.previewtemplate.PreviewTemplateHandler;
import org.onechurch.communications.domain.repository.TemplateRepository;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.util.List;
@Configuration
public class TemplateConfiguration {
    @Bean
    EmailTemplates emailTemplates(TemplateReader reader, RichText richText) {
        return new PublishedEmailTemplates(reader, richText);
    }
    @Bean
    CreateTemplateHandler createTemplateHandler(PermissionChecker auth, TemplateRepository repository,
        RichText richText, UnitOfWork tx, CommunicationAudit audit, Clock clock) {
        return new CreateTemplateHandler(auth, repository, richText, tx, audit, clock);
    }
    @Bean
    UpdateTemplateHandler updateTemplateHandler(PermissionChecker auth, TemplateRepository repository,
        RichText richText, UnitOfWork tx, CommunicationAudit audit, Clock clock) {
        return new UpdateTemplateHandler(auth, repository, richText, tx, audit, clock);
    }
    @Bean
    PublishTemplateHandler publishTemplateHandler(PermissionChecker auth, TemplateRepository repository, UnitOfWork tx,
        CommunicationAudit audit, Clock clock, List<TemplatePublicationPolicy> policies) {
        return new PublishTemplateHandler(auth, repository, tx, audit, clock, policies);
    }
    @Bean
    ArchiveTemplateHandler archiveTemplateHandler(PermissionChecker auth, TemplateRepository repository, UnitOfWork tx,
        CommunicationAudit audit, Clock clock) {
        return new ArchiveTemplateHandler(auth, repository, tx, audit, clock);
    }
    @Bean
    ListTemplatesHandler listTemplatesHandler(PermissionChecker auth, TemplateReader reader) {
        return new ListTemplatesHandler(auth, reader);
    }
    @Bean
    GetTemplateHandler getTemplateHandler(PermissionChecker auth, TemplateReader reader) {
        return new GetTemplateHandler(auth, reader);
    }
    @Bean
    ListTemplateRevisionsHandler listTemplateRevisionsHandler(PermissionChecker auth, TemplateReader reader) {
        return new ListTemplateRevisionsHandler(auth, reader);
    }
    @Bean
    PreviewTemplateHandler previewTemplateHandler(PermissionChecker auth, RichText richText) {
        return new PreviewTemplateHandler(auth, richText);
    }

}

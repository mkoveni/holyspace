package org.onechurch.communications.presentation;
import org.onechurch.communications.application.command.archivetemplate.ArchiveTemplateCommand;
import org.onechurch.communications.application.command.archivetemplate.ArchiveTemplateHandler;
import org.onechurch.communications.application.command.createtemplate.CreateTemplateCommand;
import org.onechurch.communications.application.command.createtemplate.CreateTemplateHandler;
import org.onechurch.communications.application.command.publishtemplate.PublishTemplateCommand;
import org.onechurch.communications.application.command.publishtemplate.PublishTemplateHandler;
import org.onechurch.communications.application.command.updatetemplate.UpdateTemplateCommand;
import org.onechurch.communications.application.command.updatetemplate.UpdateTemplateHandler;
import org.onechurch.communications.application.contract.TemplateReader;
import org.onechurch.communications.application.query.gettemplate.GetTemplateHandler;
import org.onechurch.communications.application.query.gettemplate.GetTemplateQuery;
import org.onechurch.communications.application.query.listtemplaterevisions.ListTemplateRevisionsHandler;
import org.onechurch.communications.application.query.listtemplaterevisions.ListTemplateRevisionsQuery;
import org.onechurch.communications.application.query.listtemplates.ListTemplatesHandler;
import org.onechurch.communications.application.query.listtemplates.ListTemplatesQuery;
import org.onechurch.communications.application.query.previewtemplate.PreviewTemplateHandler;
import org.onechurch.communications.application.query.previewtemplate.PreviewTemplateQuery;
import org.onechurch.communications.domain.TemplateContent;
import org.onechurch.communications.presentation.api.TemplateApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
public class TemplateController implements TemplateApi {
    private final CreateTemplateHandler createTemplate;
    private final UpdateTemplateHandler updateTemplate;
    private final PublishTemplateHandler publishTemplate;
    private final ArchiveTemplateHandler archiveTemplate;
    private final ListTemplatesHandler listTemplates;
    private final GetTemplateHandler getTemplate;
    private final ListTemplateRevisionsHandler listTemplateRevisions;
    private final PreviewTemplateHandler previewTemplate;

    public TemplateController(CreateTemplateHandler createTemplate, UpdateTemplateHandler updateTemplate,
        PublishTemplateHandler publishTemplate, ArchiveTemplateHandler archiveTemplate,
        ListTemplatesHandler listTemplates, GetTemplateHandler getTemplate,
        ListTemplateRevisionsHandler listTemplateRevisions, PreviewTemplateHandler previewTemplate) {
        this.createTemplate = createTemplate;
        this.updateTemplate = updateTemplate;
        this.publishTemplate = publishTemplate;
        this.archiveTemplate = archiveTemplate;
        this.listTemplates = listTemplates;
        this.getTemplate = getTemplate;
        this.listTemplateRevisions = listTemplateRevisions;
        this.previewTemplate = previewTemplate;
    }
    @Override
    @PreAuthorize("hasAuthority('communications.templates.read')")
    public TemplateReader.Page list(String search, int limit, int offset) {
        return listTemplates.handle(new ListTemplatesQuery(search, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('communications.templates.read')")
    public TemplateReader.View get(UUID id) {
        return getTemplate.handle(new GetTemplateQuery(id));
    }
    @Override
    @PreAuthorize("hasAuthority('communications.templates.manage')")
    public Created create(Draft b, OAuth2AuthenticatedPrincipal p) {
        return new Created(createTemplate.handle(new CreateTemplateCommand(UUID.fromString(p.getName()), b.key(),
            b.name(), b.subject(), b.html(), b.variables())));
    }
    @Override
    @PreAuthorize("hasAuthority('communications.templates.manage')")
    public Created update(UUID id, Edit b, OAuth2AuthenticatedPrincipal p) {
        return new Created(updateTemplate.handle(new UpdateTemplateCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.name(), b.subject(), b.html(), b.variables())));
    }
    @Override
    @PreAuthorize("hasAuthority('communications.templates.manage')")
    public Created publish(UUID id, Change b, OAuth2AuthenticatedPrincipal p) {
        return new Created(
            publishTemplate.handle(new PublishTemplateCommand(UUID.fromString(p.getName()), id, b.expectedVersion())));
    }
    @Override
    @PreAuthorize("hasAuthority('communications.templates.manage')")
    public Created archive(UUID id, Change b, OAuth2AuthenticatedPrincipal p) {
        return new Created(
            archiveTemplate.handle(new ArchiveTemplateCommand(UUID.fromString(p.getName()), id, b.expectedVersion())));
    }
    @Override
    @PreAuthorize("hasAuthority('communications.templates.read')")
    public List<TemplateReader.Revision> revisions(UUID id, int limit, int offset) {
        return listTemplateRevisions.handle(new ListTemplateRevisionsQuery(id, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('communications.templates.read')")
    public TemplateContent.Rendered preview(Preview b) {
        return previewTemplate.handle(new PreviewTemplateQuery(b.subject(), b.html(), b.variables(), b.values()));
    }
}

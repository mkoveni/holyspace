package org.onechurch.communications.application;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.communications.application.contract.RichText;
import org.onechurch.communications.application.contract.TemplateReader;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.TemplateContent;

import java.util.Map;
import java.util.stream.Collectors;
public final class PublishedEmailTemplates implements EmailTemplates {
    private final TemplateReader reader;
    private final RichText richText;
    public PublishedEmailTemplates(TemplateReader reader, RichText richText) {
        this.reader = reader;
        this.richText = richText;
    }
    public Rendered render(String key, Map<String, String> values) {
        TemplateReader.Revision revision = reader.published(key)
            .orElseThrow(() -> CommunicationException
                .conflict("Publish an active template for " + key + " before preparing this email."));
        TemplateContent.Rendered result = new TemplateContent(revision.subject(), revision.html(), revision.variables())
            .render(revision.variables().stream().collect(Collectors.toMap(name -> name, name -> {
                String value = values.get(name);
                if (value == null) {
                    throw CommunicationException.invalid("Missing template variable: " + name);
                }
                return value;
            })));
        return new Rendered(key, revision.version(), result.subject(), richText.sanitize(result.html()));
    }
}

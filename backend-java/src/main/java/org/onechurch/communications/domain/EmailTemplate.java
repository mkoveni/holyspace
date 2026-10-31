package org.onechurch.communications.domain;
import java.time.Instant;
import java.util.UUID;
public record EmailTemplate(UUID id, String key, String name, TemplateContent content, String status, long version,
    UUID updatedBy, Instant updatedAt, Long publishedVersion) {
    public EmailTemplate {
        if (key == null || !key.matches("[a-z][a-z0-9.-]{2,99}")) {
            throw CommunicationException
                .invalid("Use a template key of 3–100 lowercase letters, numbers, dots or hyphens.");
        }
        name = CommunicationRules.text(name, 150);
    }
    public EmailTemplate edit(String name, TemplateContent content, UUID actor, Instant at) {
        if (status.equals("ARCHIVED")) {
            throw CommunicationException.conflict("Archived templates cannot be edited.");
        }
        return new EmailTemplate(id, key, name, content, "DRAFT", version + 1, actor, at, publishedVersion);
    }
    public EmailTemplate publish(UUID actor, Instant at) {
        if (status.equals("ARCHIVED")) {
            throw CommunicationException.conflict("Archived templates cannot be published.");
        }
        if (status.equals("PUBLISHED")) {
            throw CommunicationException.conflict("This revision is already published.");
        }
        return new EmailTemplate(id, key, name, content, "PUBLISHED", version + 1, actor, at, version + 1);
    }
    public EmailTemplate archive(UUID actor, Instant at) {
        return new EmailTemplate(id, key, name, content, "ARCHIVED", version + 1, actor, at, publishedVersion);
    }
}
